<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\Tools\AiToolRegistry;
use App\Services\Integrations\McpToolCatalog;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpToolsController extends Controller
{
    public function index(
        Request $request,
        CurrentCompany $currentCompany,
        McpToolCatalog $catalog,
    ): JsonResponse {
        $companyId = $currentCompany->id();
        $user = $request->user();

        $tools = $catalog->describeForUser($user, $companyId);
        $availableCount = count(array_filter($tools, fn (array $t) => $t['available']));

        return response()->json([
            'data' => [
                'mode' => 'read',
                'scope' => [
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'email' => $user->email,
                ],
                'note' => 'Las tools MCP son de solo lectura. La disponibilidad de cada tool depende de los permisos del rol del usuario en la empresa activa.',
                'tools' => $tools,
                'available_count' => $availableCount,
                'total_count' => count($tools),
            ],
        ]);
    }

    public function connection(Request $request, CurrentCompany $currentCompany): JsonResponse
    {
        $companyId = $currentCompany->id();
        $base = rtrim((string) config('app.url'), '/');
        $mcpBase = $base.'/api/v1/integrations/mcp';

        $cursorExample = [
            'mcpServers' => [
                'phoenix' => [
                    'url' => $mcpBase,
                    'headers' => [
                        'Authorization' => 'Bearer <PHOENIX_MCP_TOKEN>',
                        'X-Company-Id' => (string) $companyId,
                    ],
                ],
            ],
        ];

        $httpExample = [
            'mcp_initialize' => [
                'method' => 'POST',
                'url' => $mcpBase,
                'headers' => [
                    'Authorization' => 'Bearer <PHOENIX_MCP_TOKEN>',
                    'X-Company-Id' => (string) $companyId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/event-stream',
                ],
                'body' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => '2025-03-26',
                        'capabilities' => new \stdClass,
                        'clientInfo' => ['name' => 'cursor', 'version' => '1.0.0'],
                    ],
                ],
            ],
            'list_tools_rest' => [
                'method' => 'GET',
                'url' => $mcpBase.'/tools',
                'headers' => [
                    'Authorization' => 'Bearer <PHOENIX_MCP_TOKEN>',
                    'X-Company-Id' => (string) $companyId,
                ],
            ],
            'invoke_tool_rest' => [
                'method' => 'POST',
                'url' => $mcpBase.'/tools/{tool_name}/invoke',
                'headers' => [
                    'Authorization' => 'Bearer <PHOENIX_MCP_TOKEN>',
                    'X-Company-Id' => (string) $companyId,
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'arguments' => new \stdClass,
                ],
            ],
        ];

        return response()->json([
            'data' => [
                'transport' => 'streamable_http',
                'protocol' => 'MCP JSON-RPC over HTTP (Cursor url)',
                'base_url' => $mcpBase,
                'company_id' => $companyId,
                'auth' => [
                    'type' => 'bearer',
                    'header' => 'Authorization: Bearer <token>',
                    'company_header' => 'X-Company-Id',
                    'token_ability' => 'mcp',
                    'note' => 'Genera un token MCP en Integraciones → MCP. En Cursor usa la URL /api/v1/integrations/mcp (sin /tools) y no fuerces Accept: application/json.',
                ],
                'cursor_mcp_json' => $cursorExample,
                'http_examples' => $httpExample,
            ],
        ]);
    }

    public function invoke(
        Request $request,
        string $toolName,
        CurrentCompany $currentCompany,
        AiToolRegistry $registry,
        AiToolAuthorizer $authorizer,
    ): JsonResponse {
        $data = $request->validate([
            'arguments' => ['nullable', 'array'],
        ]);

        $companyId = $currentCompany->id();
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token !== null && method_exists($token, 'can') && ! $token->can('mcp') && ! $token->can('*')) {
            // Tokens de sesión web no llevan ability mcp; se permiten para pruebas desde la UI.
            // Los tokens MCP generados sí incluyen ability "mcp".
        }

        try {
            $tool = $registry->get($toolName);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Tool MCP desconocida.'], 404);
        }

        $authorizer->assertCanUseTool($user, $companyId, $tool);

        $result = $tool->execute($data['arguments'] ?? [], $companyId);

        return response()->json([
            'data' => [
                'tool' => $toolName,
                'mode' => 'read',
                'result' => $result,
            ],
        ]);
    }
}
