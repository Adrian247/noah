<?php

namespace App\Services\AI\Contracts;

interface AiTool
{
    public function name(): string;

    public function description(): string;

    /**
     * Al menos uno de estos permisos Phoenix habilita la tool para el usuario.
     *
     * @return list<string>
     */
    public function requiredPermissions(): array;

    /**
     * JSON Schema properties for OpenAI tools.
     *
     * @return array{type: string, properties: array<string, mixed>, required?: list<string>}
     */
    public function parametersSchema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{data: mixed, sources: list<array{type: string, id: int, label: string}>}
     */
    public function execute(array $arguments, int $companyId): array;
}
