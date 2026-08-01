<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\AiTool;
use InvalidArgumentException;

class AiToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools = [];

    /**
     * @param  iterable<AiTool>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): AiTool
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Herramienta IA desconocida: {$name}");
        }

        return $this->tools[$name];
    }

    /**
     * @return list<AiTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @param  list<AiTool>  $tools
     */
    public function openAiToolSchemasFor(array $tools): array
    {
        $schemas = [];
        foreach ($tools as $tool) {
            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parametersSchema(),
                ],
            ];
        }

        return $schemas;
    }

    /**
     * OpenAI function-calling schemas.
     *
     * @return list<array<string, mixed>>
     */
    public function openAiToolSchemas(): array
    {
        return $this->openAiToolSchemasFor($this->all());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{data: mixed, sources: list<array{type: string, id: int, label: string}>}
     */
    public function execute(string $name, array $arguments, int $companyId): array
    {
        return $this->get($name)->execute($arguments, $companyId);
    }
}
