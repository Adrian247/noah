<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Integrations\McpJsonRpcHandler;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoint MCP Streamable HTTP (+ compat SSE legacy) para Cursor y clientes MCP.
 *
 * POST /api/v1/integrations/mcp  → JSON-RPC (initialize, tools/list, tools/call)
 * GET  /api/v1/integrations/mcp  → text/event-stream (endpoint event / keepalive)
 */
class McpProtocolController extends Controller
{
    public function __construct(
        private readonly McpJsonRpcHandler $handler,
    ) {}

    public function handle(Request $request, CurrentCompany $currentCompany): JsonResponse|Response|StreamedResponse
    {
        if ($request->isMethod('GET')) {
            return $this->openEventStream($request);
        }

        if ($request->isMethod('DELETE')) {
            return response()->noContent(405);
        }

        return $this->handlePost($request, $currentCompany);
    }

    private function handlePost(Request $request, CurrentCompany $currentCompany): JsonResponse|Response
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $currentCompany->id();
        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: cuerpo JSON vacío o inválido.',
                ],
            ], 400);
        }

        // Batch JSON-RPC (array de mensajes).
        if (array_is_list($payload)) {
            $responses = [];
            $hasRequest = false;
            foreach ($payload as $message) {
                if (! is_array($message)) {
                    continue;
                }
                $outcome = $this->handler->handle($message, $user, $companyId);
                if ($outcome['kind'] === 'notification_ack') {
                    continue;
                }
                $hasRequest = true;
                if (is_array($outcome['body'])) {
                    $responses[] = $outcome['body'];
                }
            }

            if (! $hasRequest) {
                return response()->noContent(202);
            }

            return response()->json(count($responses) === 1 ? $responses[0] : $responses);
        }

        $outcome = $this->handler->handle($payload, $user, $companyId);
        if ($outcome['kind'] === 'notification_ack') {
            return response()->noContent(202);
        }

        return response()->json($outcome['body'], $outcome['http_status']);
    }

    /**
     * Compatibilidad: Cursor/clientes que hacen GET esperando text/event-stream.
     * Emite el evento `endpoint` (transporte HTTP+SSE 2024-11-05) y mantiene keepalive breve.
     */
    private function openEventStream(Request $request): StreamedResponse
    {
        $accept = (string) $request->header('Accept', '');
        if ($accept !== '' && ! str_contains($accept, 'text/event-stream') && ! str_contains($accept, '*/*')) {
            abort(405, 'Este endpoint GET solo ofrece text/event-stream (MCP). Usa POST JSON-RPC para Streamable HTTP.');
        }

        $endpoint = url('/api/v1/integrations/mcp');

        return response()->stream(function () use ($endpoint): void {
            echo "event: endpoint\n";
            echo 'data: '.$endpoint."\n\n";
            echo ": phoenix-mcp\n\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
