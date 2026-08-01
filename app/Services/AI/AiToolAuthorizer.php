<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Contracts\AiTool;
use App\Services\AI\Tools\AiToolRegistry;
use App\Services\Identity\CompanyAuthorizationService;

class AiToolAuthorizer
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    /**
     * @return list<AiTool>
     */
    public function allowedTools(User $user, int $companyId, AiToolRegistry $registry): array
    {
        return array_values(array_filter(
            $registry->all(),
            fn (AiTool $tool) => $this->canUseTool($user, $companyId, $tool),
        ));
    }

    public function canUseTool(User $user, int $companyId, AiTool $tool): bool
    {
        foreach ($tool->requiredPermissions() as $permission) {
            if ($this->authorization->userHasPermission($user, $companyId, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function assertCanUseTool(User $user, int $companyId, AiTool $tool): void
    {
        if (! $this->canUseTool($user, $companyId, $tool)) {
            throw new \RuntimeException('No tienes permiso para la herramienta '.$tool->name().'.');
        }
    }
}
