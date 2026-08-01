<?php

namespace App\Ai\Support;

/**
 * Acumula fuentes y tool calls durante una invocación del agente Laravel AI.
 */
class ToolInvocationContext
{
    /** @var list<array{type: string, id: int, label: string}> */
    private array $sources = [];

    /** @var list<array{name: string, ok: bool}> */
    private array $toolCalls = [];

    /**
     * @param  list<array{type: string, id: int, label: string}>  $sources
     */
    public function addSources(array $sources): void
    {
        foreach ($sources as $source) {
            $this->sources[] = $source;
        }
    }

    public function recordTool(string $name, bool $ok): void
    {
        $this->toolCalls[] = ['name' => $name, 'ok' => $ok];
    }

    /**
     * @return list<array{type: string, id: int, label: string}>
     */
    public function sources(): array
    {
        $seen = [];
        $out = [];
        foreach ($this->sources as $source) {
            $key = $source['type'].':'.$source['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $source;
        }

        return array_slice($out, 0, 12);
    }

    /**
     * @return list<array{name: string, ok: bool}>
     */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }
}
