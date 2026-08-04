<?php

namespace App\Services\Integrations;

use App\Models\User;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\Tools\AiToolRegistry;
use InvalidArgumentException;

/**
 * Maneja mensajes JSON-RPC del protocolo MCP (tools/list, tools/call, initialize).
 */
final class McpJsonRpcHandler
{
    public const PROTOCOL_VERSION = '2025-03-26';

    /** @var list<string> */
    private const SUPPORTED_PROTOCOLS = [
        '2025-03-26',
        '2024-11-05',
    ];

    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiToolAuthorizer $authorizer,
        private readonly McpToolCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     * @return array{kind: 'response'|'notification_ack'|'error', http_status: int, body: array<string, mixed>|null}
     */
    public function handle(array $message, User $user, int $companyId): array
    {
        $jsonrpc = (string) ($message['jsonrpc'] ?? '');
        if ($jsonrpc !== '2.0') {
            return $this->errorResponse(null, -32600, 'JSON-RPC 2.0 requerido.', 400);
        }

        $method = isset($message['method']) ? (string) $message['method'] : null;
        $id = array_key_exists('id', $message) ? $message['id'] : null;
        $isNotification = $method !== null && ! array_key_exists('id', $message);

        if ($method === null) {
            return $this->errorResponse($id, -32600, 'Mensaje JSON-RPC inválido.', 400);
        }

        if ($isNotification) {
            // notifications/initialized, notifications/cancelled, etc.
            return [
                'kind' => 'notification_ack',
                'http_status' => 202,
                'body' => null,
            ];
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => new \stdClass,
                'tools/list' => $this->toolsList($user, $companyId),
                'tools/call' => $this->toolsCall($params, $user, $companyId),
                default => throw new InvalidArgumentException("Método no soportado: {$method}"),
            };
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($id, -32601, $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->errorResponse($id, -32000, $e->getMessage(), 500);
        }

        return [
            'kind' => 'response',
            'http_status' => 200,
            'body' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? self::PROTOCOL_VERSION);
        $version = in_array($requested, self::SUPPORTED_PROTOCOLS, true)
            ? $requested
            : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'phoenix',
                'version' => '1.0.0',
            ],
            'instructions' => 'Phoenix MCP (solo lectura). Usa X-Company-Id y un token con ability mcp. Las tools respetan permisos del rol en la empresa activa.',
        ];
    }

    /**
     * @return array{tools: list<array<string, mixed>>}
     */
    private function toolsList(User $user, int $companyId): array
    {
        $tools = [];
        foreach ($this->catalog->describeForUser($user, $companyId) as $row) {
            if (! ($row['available'] ?? false)) {
                continue;
            }
            $tools[] = [
                'name' => $row['name'],
                'description' => $row['description'],
                'inputSchema' => $this->normalizeInputSchema(
                    is_array($row['parameters'] ?? null) ? $row['parameters'] : [],
                ),
            ];
        }

        return ['tools' => $tools];
    }

    /**
     * Cursor valida inputSchema de forma estricta (JSON Schema object).
     * Un `properties: []` (array) hace fallar listOfferings y deja 0 tools (solo mcp_auth).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function normalizeInputSchema(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        if ($properties instanceof \stdClass) {
            $properties = (array) $properties;
        }
        if (! is_array($properties)) {
            $properties = [];
        }

        $normalizedProps = [];
        foreach ($properties as $key => $prop) {
            if (! is_string($key) || ! is_array($prop)) {
                continue;
            }
            $normalizedProps[$key] = $this->normalizePropertySchema($prop);
        }

        $out = [
            'type' => 'object',
            'properties' => $normalizedProps === [] ? new \stdClass : $normalizedProps,
        ];

        if (isset($schema['required']) && is_array($schema['required'])) {
            $required = array_values(array_filter(
                $schema['required'],
                fn ($name) => is_string($name) && array_key_exists($name, $normalizedProps),
            ));
            if ($required !== []) {
                $out['required'] = $required;
            }
        }

        $out['additionalProperties'] = false;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $prop
     * @return array<string, mixed>
     */
    private function normalizePropertySchema(array $prop): array
    {
        if (($prop['type'] ?? null) === 'integer') {
            $prop['type'] = 'number';
        }

        if (($prop['type'] ?? null) === 'array') {
            $items = $prop['items'] ?? ['type' => 'string'];
            if (is_array($items)) {
                if (($items['type'] ?? null) === 'integer') {
                    $items['type'] = 'number';
                }
                if (! isset($items['type'])) {
                    $items['type'] = 'string';
                }
                $prop['items'] = $items;
            } else {
                $prop['items'] = ['type' => 'string'];
            }
        }

        if (! isset($prop['type'])) {
            $prop['type'] = 'string';
        }

        return $prop;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function toolsCall(array $params, User $user, int $companyId): array
    {
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('params.name es requerido.');
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            $arguments = [];
        }

        try {
            $tool = $this->registry->get($name);
        } catch (InvalidArgumentException) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => "Tool desconocida: {$name}"],
                ],
                'isError' => true,
            ];
        }

        if (! $this->authorizer->canUseTool($user, $companyId, $tool)) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => "Sin permiso para usar la tool {$name} en esta empresa."],
                ],
                'isError' => true,
            ];
        }

        $result = $tool->execute($arguments, $companyId);
        $payload = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($payload === false) {
            $payload = '{"error":"No se pudo serializar el resultado"}';
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => $payload],
            ],
            'isError' => false,
        ];
    }

    /**
     * @return array{kind: 'error', http_status: int, body: array<string, mixed>}
     */
    private function errorResponse(mixed $id, int $code, string $message, int $httpStatus): array
    {
        return [
            'kind' => 'error',
            'http_status' => $httpStatus,
            'body' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ];
    }
}
