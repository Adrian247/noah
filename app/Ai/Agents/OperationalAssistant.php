<?php

namespace App\Ai\Agents;

use App\Ai\Support\ToolInvocationContext;
use App\Ai\Tools\PhoenixDomainTool;
use App\Models\User;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\Contracts\AiTool;
use App\Support\Ai\OperationalAssistantPrompt;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(5)]
#[Temperature(0.2)]
#[Timeout(60)]
class OperationalAssistant implements Agent, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * @param  list<AiTool>  $allowedDomainTools
     */
    public function __construct(
        public User $user,
        public int $companyId,
        public AiToolAuthorizer $authorizer,
        public ToolInvocationContext $context,
        public array $allowedDomainTools = [],
        public string $systemInstructions = '',
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions !== ''
            ? $this->systemInstructions
            : OperationalAssistantPrompt::default();
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return array_map(
            fn (AiTool $tool) => new PhoenixDomainTool(
                $tool,
                $this->user,
                $this->companyId,
                $this->authorizer,
                $this->context,
            ),
            $this->allowedDomainTools,
        );
    }

    protected function maxConversationMessages(): int
    {
        return 24;
    }
}
