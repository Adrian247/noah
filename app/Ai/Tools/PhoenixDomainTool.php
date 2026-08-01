<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolInvocationContext;
use App\Models\User;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\Contracts\AiTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Adapta un tool de dominio Phoenix al contrato Laravel AI, con RBAC.
 */
class PhoenixDomainTool implements Tool
{
    public function __construct(
        private readonly AiTool $domainTool,
        private readonly User $user,
        private readonly int $companyId,
        private readonly AiToolAuthorizer $authorizer,
        private readonly ToolInvocationContext $context,
    ) {}

    public function name(): string
    {
        return $this->domainTool->name();
    }

    public function description(): Stringable|string
    {
        return $this->domainTool->description();
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $this->authorizer->assertCanUseTool($this->user, $this->companyId, $this->domainTool);
            $payload = $this->domainTool->execute($request->all(), $this->companyId);
            $this->context->addSources($payload['sources'] ?? []);
            $this->context->recordTool($this->domainTool->name(), true);

            return (string) json_encode($payload['data'] ?? $payload, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->context->recordTool($this->domainTool->name(), false);

            return (string) json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        $definition = $this->domainTool->parametersSchema();
        $properties = is_array($definition['properties'] ?? null) ? $definition['properties'] : [];
        $required = is_array($definition['required'] ?? null) ? $definition['required'] : [];
        $out = [];

        foreach ($properties as $key => $propertySchema) {
            if (! is_array($propertySchema)) {
                continue;
            }
            $type = JsonSchemaFactory::fromArray($propertySchema);
            if (in_array($key, $required, true)) {
                $type->required();
            }
            $out[$key] = $type;
        }

        return $out;
    }
}
