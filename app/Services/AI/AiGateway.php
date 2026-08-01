<?php

namespace App\Services\AI;

use App\Models\AiInvocation;
use App\Models\PromptTemplate;
use App\Models\Routine;
use App\Models\User;
use App\Services\AI\Contracts\AiTool;
use App\Services\AI\Tools\AiToolRegistry;
use App\Support\CurrentCompany;
use Illuminate\Support\Str;

class AiGateway
{
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private readonly GrammarCorrectionService $localGrammar,
        private readonly AiToolRegistry $tools,
        private readonly AiProviderResolver $providerResolver,
        private readonly AiQuotaService $quotas,
        private readonly AiToolAuthorizer $toolAuthorizer,
        private readonly AiPlatformSettingsService $platformSettings,
        private readonly LaravelAiAssistantRunner $laravelAssistant,
        private readonly AssistantDashboardBuilder $dashboardBuilder,
    ) {}

    private function provider(): \App\Services\AI\Contracts\AiProviderContract
    {
        return $this->providerResolver->resolve();
    }

    public function correctGrammar(string $text, ?int $userId = null): string
    {
        $companyId = app(CurrentCompany::class)->id();
        $template = PromptTemplate::activeFor('grammar_correction_v1', $companyId);

        if ($this->provider()->supportsChat()) {
            try {
                $this->quotas->assertTokenQuota($companyId);
                $system = $template?->system_prompt ?? 'Corrige gramática sin agregar datos.';
                $userPrompt = str_replace('{{technician_text}}', $text, $template?->user_template ?? '{{technician_text}}');
                $result = $this->provider()->chat(
                    [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    [],
                    $template?->model,
                    (float) ($template?->temperature ?? 0.2),
                );
                if ($result->content !== '') {
                    $this->logInvocation(
                        $companyId,
                        $userId,
                        'grammar_correction',
                        $result->provider,
                        $result->model,
                        $text,
                        $result->content,
                        'success',
                        $result->inputTokens,
                        $result->outputTokens,
                    );

                    return $result->content;
                }
            } catch (\Throwable $e) {
                $this->logInvocation($companyId, $userId, 'grammar_correction', $this->provider()->name(), null, $text, null, 'failed');
                report($e);
            }
        }

        $output = $this->localGrammar->correct($text);
        $this->logInvocation($companyId, $userId, 'grammar_correction', 'local', null, $text, $output, 'success');

        return $output;
    }

    /**
     * Asistente operativo con tools de solo lectura (grounding).
     *
     * @param  list<array{role: string, text: string}>  $history
     * @return array{
     *     answer: string,
     *     sources: list<array{type: string, id: int, label: string}>,
     *     provider: string,
     *     tool_calls: list<array{name: string, ok: bool}>,
     *     conversation_id: ?string,
     *     presentation: ?array{type: string, title: string, content: array<string, mixed>}
     * }|null
     */
    public function invokeAssistant(
        string $question,
        int $companyId,
        User $user,
        ?int $userId = null,
        ?string $pageContext = null,
        ?string $conversationId = null,
        array $history = [],
    ): ?array {
        $template = PromptTemplate::activeFor('insights_assistant_v1', $companyId);
        $system = $template?->system_prompt ?? <<<'PROMPT'
Eres un asistente operativo de Phoenix. Responde en español, breve y factual.
Usa SOLO datos obtenidos de las herramientas. No inventes rutinas, activos, montos ni IDs.
Si el usuario pide KPIs, dashboard o indicadores, usa get_operational_kpis.
Si faltan datos, dilo. Cita IDs presentes en los resultados de herramientas.
PROMPT;

        $enrichedQuestion = $pageContext !== null && trim($pageContext) !== ''
            ? trim($question)."\n\nContexto de pantalla: ".$pageContext
            : $question;

        $preferDashboard = $this->dashboardBuilder->wantsDashboard($question);
        $llmFailed = false;

        if ($this->laravelAssistant->isAvailable()) {
            try {
                $this->quotas->assertTokenQuota($companyId);
                $result = $this->laravelAssistant->run(
                    $enrichedQuestion,
                    $companyId,
                    $user,
                    $system,
                    $conversationId,
                    $preferDashboard,
                );

                $this->logInvocation(
                    $companyId,
                    $userId,
                    'insights_assistant',
                    $result['provider'],
                    $result['model'],
                    $enrichedQuestion,
                    $result['answer'],
                    'success',
                    $result['input_tokens'],
                    $result['output_tokens'],
                    $result['tool_calls'],
                );

                return [
                    'answer' => $result['answer'],
                    'sources' => $result['sources'],
                    'provider' => $result['provider'],
                    'tool_calls' => $result['tool_calls'],
                    'conversation_id' => $result['conversation_id'] ?? $conversationId,
                    'presentation' => $result['presentation'] ?? null,
                ];
            } catch (\Throwable $e) {
                $llmFailed = true;
                $this->logInvocation(
                    $companyId,
                    $userId,
                    'insights_assistant',
                    $this->platformSettings->get()['provider'],
                    null,
                    $enrichedQuestion,
                    null,
                    'failed',
                );
                report($e);
            }
        }

        $local = $this->runGroundedLocalAssistant(
            $enrichedQuestion,
            $companyId,
            $user,
            $userId,
            $pageContext,
            $conversationId,
            $history,
            $preferDashboard,
        );

        if ($llmFailed && is_string($local['answer'] ?? null)) {
            $local['answer'] = "El proveedor LLM no respondió; usé datos locales verificados.\n\n".$local['answer'];
        }

        return $local;
    }

    /**
     * @deprecated Use invokeAssistant()
     */
    public function answerOperationalQuestion(
        string $question,
        string $context,
        ?int $companyId = null,
        ?int $userId = null,
    ): ?string {
        if ($companyId === null) {
            return null;
        }

        $authUser = auth()->user();
        if (! $authUser instanceof User) {
            return null;
        }

        $result = $this->invokeAssistant($question, $companyId, $authUser, $userId);

        return $result['answer'] ?? null;
    }

    public function extractPlateText(string $imageContents, ?int $userId = null): string
    {
        $companyId = app(CurrentCompany::class)->id();
        $this->quotas->assertVisionQuota($companyId);

        $template = PromptTemplate::activeFor('vision_ocr_v1', $companyId);
        $prompt = $template?->system_prompt
            ?? 'Extrae texto visible de placa o etiqueta. Solo el texto, sin explicación.';

        if (! $this->provider()->supportsVision()) {
            $msg = 'OCR no disponible: el proveedor actual no soporta visión. Configura OpenAI con visión en Configuración → Asistente IA, o usa un proveedor con OCR.';
            $this->logInvocation($companyId, $userId, 'vision_ocr', $this->provider()->name(), null, 'vision', $msg, 'failed');

            throw new \RuntimeException($msg);
        }

        try {
            $result = $this->provider()->visionExtractText(
                $imageContents,
                $prompt,
                $template?->model ?? config('phoenix.ai.openai.vision_model'),
            );
            $text = $result->content !== '' ? $result->content : '—';
            $this->logInvocation(
                $companyId,
                $userId,
                'vision_ocr',
                $result->provider,
                $result->model,
                'vision',
                $text,
                'success',
                $result->inputTokens,
                $result->outputTokens,
            );

            return $text;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logInvocation($companyId, $userId, 'vision_ocr', $this->provider()->name(), null, 'vision', null, 'failed');
            report($e);

            throw new \RuntimeException('OCR falló; reintente o revise la configuración del proveedor.', 0, $e);
        }
    }

    public function generateReportNarrative(Routine $routine, ?int $userId = null): string
    {
        $routine->loadMissing(['asset.catalogItem', 'routineType', 'latestExecution', 'company']);
        $execution = $routine->latestExecution;
        $responses = is_array($execution?->responses) ? $execution->responses : [];

        $bullets = [];
        foreach ($responses as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $bullets[] = sprintf('%s: %s', (string) $key, (string) ($value ?? '—'));
            }
        }

        $draft = sprintf(
            "Se completó la rutina #%d (%s) en el activo %s.\n\nHallazgos registrados:\n- %s\n\n%s",
            $routine->id,
            $routine->routineType?->name ?? 'Servicio',
            $routine->asset?->tag ?? '—',
            $bullets === [] ? 'Sin campos adicionales.' : implode("\n- ", $bullets),
            $execution?->technician_comments ? 'Comentario del técnico: '.$execution->technician_comments : '',
        );

        $this->logInvocation(
            $routine->company_id,
            $userId,
            'report_narrative',
            'local',
            null,
            'routine:'.$routine->id,
            $draft,
            'success',
            null,
            null,
            [['name' => 'get_routine', 'ok' => true, 'routine_id' => $routine->id]],
        );

        return $draft;
    }

    /**
     * @return array{answer: string, sources: list<array{type: string, id: int, label: string}>, provider: string, tool_calls: list<array{name: string, ok: bool}>}
     */
    private function runToolCallingAssistant(
        string $question,
        int $companyId,
        User $user,
        ?int $userId,
        string $system,
        ?PromptTemplate $template,
    ): array {
        $allowedTools = $this->toolAuthorizer->allowedTools($user, $companyId, $this->tools);
        if ($allowedTools === []) {
            return [
                'answer' => 'No tienes permisos para consultar datos operativos con el asistente.',
                'sources' => [],
                'provider' => $this->provider()->name(),
                'tool_calls' => [],
            ];
        }

        $platform = $this->platformSettings->get();
        $model = match ($this->provider()->name()) {
            'google' => $platform['google_model'],
            'openai' => $platform['openai_model'],
            default => $template?->model,
        };

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ];
        $schemas = $this->tools->openAiToolSchemasFor($allowedTools);
        $sources = [];
        $toolTrace = [];
        $inputTokens = 0;
        $outputTokens = 0;
        $model = $model ?? $template?->model;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $result = $this->provider()->chat(
                $messages,
                $schemas,
                $model,
                (float) ($template?->temperature ?? 0.2),
            );
            $inputTokens += (int) ($result->inputTokens ?? 0);
            $outputTokens += (int) ($result->outputTokens ?? 0);
            $model = $result->model ?? $model;

            if (! $result->hasToolCalls()) {
                $answer = $result->content !== ''
                    ? $result->content
                    : 'No hay datos suficientes en las herramientas para responder.';

                $this->logInvocation(
                    $companyId,
                    $userId,
                    'insights_assistant',
                    $result->provider,
                    $model,
                    $question,
                    $answer,
                    'success',
                    $inputTokens,
                    $outputTokens,
                    $toolTrace,
                );

                return [
                    'answer' => $answer,
                    'sources' => $this->uniqueSources($sources),
                    'provider' => $result->provider,
                    'tool_calls' => $toolTrace,
                ];
            }

            $assistantMsg = [
                'role' => 'assistant',
                'content' => $result->content !== '' ? $result->content : null,
                'tool_calls' => array_map(static fn (array $call) => [
                    'id' => $call['id'] ?: ('call_'.Str::random(8)),
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'],
                        'arguments' => json_encode($call['arguments'], JSON_UNESCAPED_UNICODE),
                    ],
                ], $result->toolCalls),
            ];
            $messages[] = $assistantMsg;

            foreach ($result->toolCalls as $call) {
                $callId = $call['id'] !== '' ? $call['id'] : ('call_'.Str::random(8));
                try {
                    $tool = $this->findAllowedTool($allowedTools, $call['name']);
                    $this->toolAuthorizer->assertCanUseTool($user, $companyId, $tool);
                    $payload = $tool->execute($call['arguments'], $companyId);
                    $ok = true;
                    $sources = array_merge($sources, $payload['sources'] ?? []);
                    $content = json_encode($payload['data'], JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    $ok = false;
                    $content = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
                $toolTrace[] = ['name' => $call['name'], 'ok' => $ok];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => $content,
                ];
            }
        }

        $fallback = $this->formatFromToolTrace($toolTrace, $sources);
        $this->logInvocation(
            $companyId,
            $userId,
            'insights_assistant',
            $this->provider()->name(),
            $model,
            $question,
            $fallback,
            'success',
            $inputTokens,
            $outputTokens,
            $toolTrace,
        );

        return [
            'answer' => $fallback,
            'sources' => $this->uniqueSources($sources),
            'provider' => $this->provider()->name(),
            'tool_calls' => $toolTrace,
        ];
    }

    /**
     * Sin LLM: ejecuta tools por heurística y formatea solo con datos reales.
     *
     * @param  list<array{role: string, text: string}>  $history
     * @return array{
     *     answer: string,
     *     sources: list<array{type: string, id: int, label: string}>,
     *     provider: string,
     *     tool_calls: list<array{name: string, ok: bool}>,
     *     conversation_id: ?string,
     *     presentation: ?array{type: string, title: string, content: array<string, mixed>}
     * }
     */
    private function runGroundedLocalAssistant(
        string $question,
        int $companyId,
        User $user,
        ?int $userId,
        ?string $pageContext = null,
        ?string $conversationId = null,
        array $history = [],
        bool $preferDashboard = false,
    ): array {
        $normalized = Str::lower(Str::ascii(trim($question)));
        if ($history !== []) {
            $recent = collect($history)
                ->slice(-6)
                ->map(fn (array $row) => (string) ($row['text'] ?? ''))
                ->implode(' ');
            $normalized = Str::lower(Str::ascii(trim($normalized.' '.$recent)));
        }

        $toolPlan = $this->selectLocalToolPlan($normalized, $pageContext, $user, $companyId);
        if ($toolPlan === []) {
            return [
                'answer' => 'No tienes permisos para consultar datos con el asistente en tu rol actual.',
                'sources' => [],
                'provider' => 'local',
                'tool_calls' => [],
                'conversation_id' => $conversationId ?: (string) Str::uuid(),
                'presentation' => null,
            ];
        }

        $sources = [];
        $sections = [];
        $toolTrace = [];
        $kpiPayload = null;

        foreach ($toolPlan as $plan) {
            $name = $plan['name'];
            $args = $plan['arguments'];
            try {
                $tool = $this->tools->get($name);
                $this->toolAuthorizer->assertCanUseTool($user, $companyId, $tool);
                $payload = $tool->execute($args, $companyId);
                $toolTrace[] = ['name' => $name, 'ok' => true];
                $sources = array_merge($sources, $payload['sources'] ?? []);
                if ($name === 'get_operational_kpis' && is_array($payload['data'] ?? null)) {
                    $kpiPayload = $payload['data'];
                }
                $formatted = $this->formatLocalToolAnswer($name, $payload['data']);
                if ($formatted !== '') {
                    $sections[] = $formatted;
                }
            } catch (\Throwable $e) {
                $toolTrace[] = ['name' => $name, 'ok' => false];
            }
        }

        if ($sections === []) {
            $answer = 'Puedo ayudarte con rutinas, clientes, facturas, sitios, KPIs, auditoría, activos o insumos. Prueba: «Muéstrame el dashboard de KPIs»';
        } else {
            $answer = implode("\n\n", $sections);
        }

        $presentation = null;
        if ($preferDashboard || $kpiPayload !== null) {
            if ($kpiPayload === null) {
                try {
                    $tool = $this->tools->get('get_operational_kpis');
                    if ($this->toolAuthorizer->canUseTool($user, $companyId, $tool)) {
                        $payload = $tool->execute([], $companyId);
                        $kpiPayload = is_array($payload['data'] ?? null) ? $payload['data'] : null;
                        $sources = array_merge($sources, $payload['sources'] ?? []);
                        $toolTrace[] = ['name' => 'get_operational_kpis', 'ok' => true];
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
            if (is_array($kpiPayload)) {
                $presentation = $this->dashboardBuilder->fromOperationalKpis($kpiPayload);
                if ($preferDashboard && $presentation !== null) {
                    $answer = 'Aquí tienes el tablero de KPIs operativos con datos verificados de tu empresa.';
                }
            }
        }

        $this->logInvocation(
            $companyId,
            $userId,
            'insights_assistant',
            'local',
            null,
            $question,
            $answer,
            'success',
            null,
            null,
            $toolTrace,
        );

        return [
            'answer' => $answer,
            'sources' => $this->uniqueSources($sources),
            'provider' => 'local',
            'tool_calls' => $toolTrace,
            'conversation_id' => $conversationId ?: (string) Str::uuid(),
            'presentation' => $presentation,
        ];
    }

    /**
     * @return list<array{name: string, arguments: array<string, mixed>}>
     */
    private function selectLocalToolPlan(string $normalized, ?string $pageContext, User $user, int $companyId): array
    {
        $plan = $this->buildLocalToolPlan($normalized, $pageContext);

        return array_values(array_filter($plan, function (array $item) use ($user, $companyId) {
            try {
                $tool = $this->tools->get($item['name']);

                return $this->toolAuthorizer->canUseTool($user, $companyId, $tool);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    /**
     * @return list<array{name: string, arguments: array<string, mixed>}>
     */
    private function buildLocalToolPlan(string $normalized, ?string $pageContext): array
    {
        if (preg_match('/Rutina en contexto:\s*#(\d+)/i', (string) $pageContext, $m)) {
            if (Str::contains($normalized, ['esta', 'actual', 'detalle', 'resumen', 'rutina'])) {
                return [['name' => 'get_routine', 'arguments' => ['routine_id' => (int) $m[1]]]];
            }
        }

        if (preg_match('/rutina\s*#?(\d+)/', $normalized, $m)) {
            return [['name' => 'get_routine', 'arguments' => ['routine_id' => (int) $m[1]]]];
        }

        if ($this->dashboardBuilder->wantsDashboard($normalized)
            || Str::contains($normalized, ['kpi', 'indicador', 'tablero', 'metrica', 'dashboard'])) {
            return [['name' => 'get_operational_kpis', 'arguments' => []]];
        }

        $selected = [];
        if (Str::contains($normalized, ['cliente', 'clientes', 'razon social', 'rfc'])) {
            $selected[] = ['name' => 'list_clients', 'arguments' => ['limit' => 10]];
        }
        if (Str::contains($normalized, ['factura', 'facturas', 'invoice', 'cfdi', 'facturacion'])) {
            $selected[] = ['name' => 'list_invoices', 'arguments' => ['limit' => 10]];
        }
        if (Str::contains($normalized, ['sitio', 'sitios', 'ubicacion', 'ubicaciones', 'planta'])) {
            $selected[] = ['name' => 'list_sites', 'arguments' => ['limit' => 10]];
        }
        if (Str::contains($normalized, ['rutina', 'orden', 'servicio'])) {
            $selected[] = ['name' => 'list_recent_routines', 'arguments' => ['limit' => 8]];
        }
        if (Str::contains($normalized, ['auditor', 'historial', 'evento'])) {
            $selected[] = ['name' => 'list_audit_entries', 'arguments' => ['limit' => 5]];
        }
        if (Str::contains($normalized, ['activo', 'equipo', 'tag', 'etiqueta'])) {
            $selected[] = ['name' => 'search_assets', 'arguments' => ['limit' => 5]];
        }
        if (Str::contains($normalized, ['insumo', 'refaccion', 'repuesto', 'supply'])) {
            $selected[] = ['name' => 'list_supply_items', 'arguments' => ['limit' => 10]];
        }
        if ($selected === []) {
            $selected[] = ['name' => 'list_recent_routines', 'arguments' => ['limit' => 8]];
        }

        return $selected;
    }

    private function formatLocalToolAnswer(string $toolName, mixed $data): string
    {
        if (! is_array($data)) {
            return (string) $data;
        }

        if (isset($data['error'])) {
            return (string) $data['error'];
        }

        if ($toolName === 'get_routine') {
            return sprintf(
                "Rutina #%s (%s) en activo %s, estado %s. Actualizada: %s.",
                $data['id'] ?? '—',
                $data['type'] ?? '—',
                $data['asset_tag'] ?? '—',
                $data['status'] ?? '—',
                $data['updated_at'] ?? '—',
            );
        }

        if ($toolName === 'get_operational_kpis') {
            $routines = is_array($data['routines'] ?? null) ? $data['routines'] : [];
            $invoices = is_array($data['invoices'] ?? null) ? $data['invoices'] : [];
            $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];

            return sprintf(
                "KPIs operativos:\n- Rutinas: %s (completitud %s%%)\n- Activos: %s\n- Facturado emitido: %s MXN",
                $routines['total'] ?? 0,
                $routines['completion_pct'] ?? 0,
                $assets['total'] ?? 0,
                number_format((float) ($invoices['issued_amount'] ?? 0), 2),
            );
        }

        if ($data === []) {
            return match ($toolName) {
                'list_recent_routines' => 'No hay rutinas recientes registradas.',
                'list_audit_entries' => 'No hay eventos de auditoría recientes.',
                'search_assets' => 'No encontré activos con ese criterio.',
                'list_supply_items' => 'No hay insumos en el catálogo.',
                'list_clients' => 'No hay clientes registrados.',
                'list_invoices' => 'No hay facturas registradas.',
                'list_sites' => 'No hay sitios registrados.',
                default => 'Sin resultados.',
            };
        }

        $lines = match ($toolName) {
            'list_recent_routines' => collect($data)->map(fn (array $row) => sprintf(
                '- Rutina #%s (%s) en %s — estado %s',
                $row['id'] ?? '—',
                $row['type'] ?? '—',
                $row['asset_tag'] ?? '—',
                $row['status'] ?? '—',
            )),
            'list_audit_entries' => collect($data)->map(fn (array $row) => sprintf(
                '- %s sobre %s#%s (%s)',
                $row['action'] ?? '—',
                $row['entity_type'] ?? '—',
                $row['entity_id'] ?? '—',
                $row['occurred_at'] ?? '—',
            )),
            'search_assets' => collect($data)->map(fn (array $row) => sprintf(
                '- Activo #%s · etiqueta %s',
                $row['id'] ?? '—',
                $row['tag'] ?? '—',
            )),
            'list_supply_items' => collect($data)->map(fn (array $row) => sprintf(
                '- %s (SKU %s)',
                $row['name'] ?? '—',
                $row['sku'] ?? '—',
            )),
            'list_clients' => collect($data)->map(fn (array $row) => sprintf(
                '- #%s %s%s',
                $row['id'] ?? '—',
                $row['trade_name'] ?: ($row['legal_name'] ?? 'Cliente'),
                ! empty($row['code']) ? ' ('.$row['code'].')' : '',
            )),
            'list_invoices' => collect($data)->map(fn (array $row) => sprintf(
                '- Factura #%s · %s · %s · $%s',
                $row['id'] ?? '—',
                $row['number'] ?? ($row['folio'] ?? '—'),
                $row['status'] ?? '—',
                number_format((float) ($row['total'] ?? 0), 2),
            )),
            'list_sites' => collect($data)->map(fn (array $row) => sprintf(
                '- Sitio #%s · %s',
                $row['id'] ?? '—',
                $row['name'] ?? '—',
            )),
            default => collect($data)->map(fn ($row) => '- '.$this->stringifyData($row)),
        };

        $heading = match ($toolName) {
            'list_recent_routines' => 'Rutinas recientes:',
            'list_audit_entries' => 'Eventos de auditoría:',
            'search_assets' => 'Activos:',
            'list_supply_items' => 'Insumos del catálogo:',
            'list_clients' => 'Clientes:',
            'list_invoices' => 'Facturas:',
            'list_sites' => 'Sitios:',
            default => 'Resultados:',
        };

        return $heading."\n".$lines->implode("\n");
    }

    private function stringifyData(mixed $data): string
    {
        if (! is_array($data)) {
            return (string) $data;
        }
        if ($data === []) {
            return '(sin resultados)';
        }

        $lines = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                $lines[] = '- '.(string) $row;

                continue;
            }
            $parts = [];
            foreach ($row as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $parts[] = $k.'='.(string) ($v ?? '—');
                }
            }
            $lines[] = '- '.implode(' | ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{name: string, ok: bool}>  $toolTrace
     * @param  list<array{type: string, id: int, label: string}>  $sources
     */
    private function formatFromToolTrace(array $toolTrace, array $sources): string
    {
        if ($sources === []) {
            return 'Se consultaron herramientas pero no hubo resultados citables.';
        }

        $labels = array_map(static fn (array $s) => $s['label'], $sources);

        return "Resumen basado en herramientas (".implode(', ', array_column($toolTrace, 'name'))."):\n- "
            .implode("\n- ", array_slice($labels, 0, 10));
    }

    /**
     * @param  list<array{type: string, id: int, label: string}>  $sources
     * @return list<array{type: string, id: int, label: string}>
     */
    private function uniqueSources(array $sources): array
    {
        $seen = [];
        $out = [];
        foreach ($sources as $source) {
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
     * @param  list<AiTool>  $allowedTools
     */
    private function findAllowedTool(array $allowedTools, string $name): AiTool
    {
        foreach ($allowedTools as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        throw new \RuntimeException('Herramienta no permitida: '.$name);
    }

    /**
     * @param  list<array{name: string, ok: bool}>|null  $toolCalls
     */
    private function logInvocation(
        ?int $companyId,
        ?int $userId,
        string $useCase,
        string $provider,
        ?string $model,
        string $input,
        ?string $output,
        string $status,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?array $toolCalls = null,
    ): void {
        if ($companyId === null) {
            return;
        }

        AiInvocation::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'use_case' => $useCase,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_excerpt' => Str::limit($input, 500),
            'output_excerpt' => $output !== null ? Str::limit($output, 500) : null,
            'status' => $status,
            'tool_calls' => $toolCalls,
        ]);
    }
}
