<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class McpTokenController extends Controller
{
    public const TOKEN_ABILITY = 'mcp';

    public const TOKEN_PREFIX = 'mcp:';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->tokens()
            ->where('name', 'like', self::TOKEN_PREFIX.'%')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => $this->formatToken($token));

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request, CurrentCompany $currentCompany, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $companyId = $currentCompany->id();
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = 'MCP '.now()->format('Y-m-d H:i');
        }

        $name = self::TOKEN_PREFIX.$label;

        $newToken = $user->createToken($name, [self::TOKEN_ABILITY]);
        $plain = $newToken->plainTextToken;
        $token = $newToken->accessToken;

        $audit->record(
            $companyId,
            $user->id,
            'integrations.mcp_token_created',
            PersonalAccessToken::class,
            $token->id,
            ['label' => $label],
            $request->ip(),
        );

        return response()->json([
            'data' => array_merge($this->formatToken($token), [
                'token' => $plain,
                'company_id' => $companyId,
                'note' => 'Copia el token ahora; no se volverá a mostrar. Úsalo con X-Company-Id de la empresa activa al generarlo (u otra membresía válida del mismo usuario).',
            ]),
        ], 201);
    }

    public function destroy(Request $request, int $tokenId, CurrentCompany $currentCompany, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()
            ->where('id', $tokenId)
            ->where('name', 'like', self::TOKEN_PREFIX.'%')
            ->firstOrFail();

        $audit->record(
            $currentCompany->id(),
            $user->id,
            'integrations.mcp_token_revoked',
            PersonalAccessToken::class,
            $token->id,
            ['label' => $this->labelFromName((string) $token->name)],
            $request->ip(),
        );

        $token->delete();

        return response()->json(['message' => 'Token MCP revocado.']);
    }

    /**
     * @return array{id: int, label: string, name: string, abilities: list<string>, last_used_at: ?string, created_at: ?string}
     */
    private function formatToken(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'label' => $this->labelFromName((string) $token->name),
            'name' => (string) $token->name,
            'abilities' => array_values($token->abilities ?? []),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }

    private function labelFromName(string $name): string
    {
        return str_starts_with($name, self::TOKEN_PREFIX)
            ? substr($name, strlen(self::TOKEN_PREFIX))
            : $name;
    }
}
