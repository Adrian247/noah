<?php

namespace App\Services\AI;

use App\Models\AiInvocation;
use App\Models\PromptTemplate;
use App\Models\Routine;
use App\Models\User;
use App\Services\AI\Contracts\AiProviderContract;
use App\Services\AI\Contracts\AiTool;
use App\Services\AI\Tools\AiToolRegistry;
use App\Support\Ai\OperationalAssistantPrompt;
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

    private function provider(): AiProviderContract
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
        $system = $template?->system_prompt ?? OperationalAssistantPrompt::default();

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

        $clarifyPrediction = $this->predictiveClarificationNeeded($normalized);
        if ($clarifyPrediction !== null) {
            return [
                'answer' => $clarifyPrediction,
                'sources' => [],
                'provider' => 'local',
                'tool_calls' => [],
                'conversation_id' => $conversationId ?: (string) Str::uuid(),
                'presentation' => null,
            ];
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
            $answer = 'Puedo ayudarte con rutinas (mantenimiento, manufactura o suministro), clientes, facturas, sitios, KPIs, predicción de equipos o demanda a clientes. Prueba: «Muéstrame el dashboard de KPIs» o «Demanda de manufactura».';
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

        $predictive = $this->buildPredictiveToolPlan($normalized);
        if ($predictive !== []) {
            return $predictive;
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

    /**
     * Ruteo de intención predictiva para el proveedor local (sin LLM).
     *
     * Demanda de manufactura/suministro → predict_client_demand.
     * Tag concreto → ficha de salud; flota/clase → ranking de riesgo de equipos.
     *
     * @return list<array{name: string, arguments: array<string, mixed>}>
     */
    private function buildPredictiveToolPlan(string $normalized): array
    {
        if ($this->wantsClientDemandIntent($normalized)) {
            $arguments = ['limit' => 10];
            if (Str::contains($normalized, ['suministro', 'insumo', 'compra a cliente', 'reventa'])) {
                $arguments['service_line'] = 'supply';
            } elseif (Str::contains($normalized, [
                'manufactura', 'fabricacion', 'produccion', 'obra', 'bordado', 'textil',
            ])) {
                $arguments['service_line'] = 'fabrication';
            }

            return [['name' => 'predict_client_demand', 'arguments' => $arguments]];
        }

        if (! $this->wantsEquipmentPredictiveIntent($normalized)) {
            return [];
        }

        if (Str::contains($normalized, ['modo de falla', 'modos de falla', 'taxonomia', 'catalogo de fallas'])) {
            return [['name' => 'list_failure_modes', 'arguments' => []]];
        }

        // Sin equipo/clase/flota el plan queda vacío: answerWithLocalTools ya aclaró.
        if ($this->predictiveClarificationNeeded($normalized) !== null) {
            return [];
        }

        $horizon = preg_match('/(\d{1,2})\s*dias/', $normalized, $days) === 1 ? (int) $days[1] : null;

        // Un tag explícito (SS-305, JB 101, VQ207) desplaza la consulta al equipo puntual.
        if (preg_match('/\b([a-z]{2,4})[\s\-_]?(\d{2,4})\b/', $normalized, $tag) === 1) {
            return [[
                'name' => 'get_equipment_health',
                'arguments' => ['tag' => strtoupper($tag[1]).'-'.$tag[2]],
            ]];
        }

        $arguments = ['limit' => 10];
        if ($horizon !== null) {
            $arguments['horizon_days'] = $horizon;
        }

        $class = $this->extractEquipmentClassFromQuestion($normalized);
        if ($class !== null) {
            $arguments['equipment_class'] = $class;
        }

        return [['name' => 'predict_equipment_failures', 'arguments' => $arguments]];
    }

    private function wantsClientDemandIntent(string $normalized): bool
    {
        return Str::contains($normalized, [
            'demanda',
            'manufactura',
            'fabricacion',
            'suministro',
            'que cliente',
            'cual cliente',
            'clientes pediran',
            'cliente pedira',
            'pedido de cliente',
            'orden de produccion',
            'orden de manufactura',
            'predict_client',
            'servicio a cliente',
            'trabajos a cliente',
        ]);
    }

    private function wantsEquipmentPredictiveIntent(string $normalized): bool
    {
        if ($this->wantsClientDemandIntent($normalized)) {
            return false;
        }

        return Str::contains($normalized, [
            'predic', 'va a fallar', 'por fallar', 'riesgo', 'probabilidad de falla',
            'fallas esperadas', 'proxima falla', 'mantenimiento predictivo', 'salud del equipo',
        ]);
    }

    /**
     * Si el usuario pide predicción sin indicar equipo, clase, flota ni demanda de cliente, se pide aclaración.
     */
    private function predictiveClarificationNeeded(string $normalized): ?string
    {
        if ($this->wantsClientDemandIntent($normalized)) {
            return null;
        }

        if (! $this->wantsEquipmentPredictiveIntent($normalized)) {
            return null;
        }

        if (Str::contains($normalized, ['modo de falla', 'modos de falla', 'taxonomia', 'catalogo de fallas'])) {
            return null;
        }

        if (preg_match('/\b([a-z]{2,4})[\s\-_]?(\d{2,4})\b/', $normalized) === 1) {
            return null;
        }

        if ($this->extractEquipmentClassFromQuestion($normalized) !== null) {
            return null;
        }

        if (Str::contains($normalized, ['toda la flota', 'todas las flotas', 'todos los equipos', 'flota completa', 'parque completo'])) {
            return null;
        }

        return 'Puedo estimar dos cosas distintas: '
            .'1) Riesgo de falla en equipos (mantenimiento) — indica el tag (p. ej. SS-305), la clase '
            .'(scooptram, camión, jumbo…) o di «toda la flota». '
            .'2) Demanda de manufactura o suministro a clientes — di «demanda de clientes», «manufactura» o «suministro». '
            .'El análisis de equipos usa rutinas de mantenimiento; el de demanda, rutinas ligadas a cliente.';
    }

    private function extractEquipmentClassFromQuestion(string $normalized): ?string
    {
        $map = [
            'scooptram' => 'SCOOPTRAM',
            'scoop' => 'SCOOPTRAM',
            'lhd' => 'SCOOPTRAM',
            'camion' => 'CAMION_BAJO_PERFIL',
            'jumbo' => 'JUMBO',
            'quebradora' => 'QUEBRADORA',
            'molino' => 'MOLINO',
            'filtro' => 'FILTRO',
            'banda' => 'BANDA_TRANSPORTADORA',
            'bomba' => 'BOMBA',
        ];

        foreach ($map as $needle => $class) {
            if (Str::contains($normalized, $needle)) {
                return $class;
            }
        }

        return null;
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
            $subject = $data['asset_tag']
                ?? (isset($data['client_name']) ? 'cliente '.$data['client_name'] : null)
                ?? 'sin sujeto';
            $line = isset($data['service_line_label']) ? ' · '.$data['service_line_label'] : '';

            return sprintf(
                'Rutina #%s (%s%s) en %s, estado %s. Actualizada: %s.',
                $data['id'] ?? '—',
                $data['type'] ?? '—',
                $line,
                $subject,
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

        if ($toolName === 'predict_equipment_failures') {
            return $this->formatPredictions($data);
        }

        if ($toolName === 'predict_client_demand') {
            return $this->formatClientDemand($data);
        }

        if ($toolName === 'get_equipment_health') {
            return $this->formatEquipmentHealth($data);
        }

        if ($toolName === 'list_failure_modes') {
            $modes = collect($data['failure_modes'] ?? [])->take(12)->map(fn (array $mode) => sprintf(
                '- %s · %s (%s)',
                $mode['code'] ?? '—',
                $mode['name'] ?? '—',
                $mode['system'] ?? '—',
            ))->implode("\n");

            return $modes === ''
                ? 'El catálogo de modos de falla está vacío.'
                : sprintf("Modos de falla (%d en total):\n%s", (int) ($data['total'] ?? 0), $modes);
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
            'list_recent_routines' => collect($data)->map(function (array $row) {
                $subject = $row['asset_tag']
                    ?? (isset($row['client_name']) ? 'cliente '.$row['client_name'] : null)
                    ?? 'sin sujeto';
                $line = isset($row['service_line_label']) ? ' · '.$row['service_line_label'] : '';

                return sprintf(
                    '- Rutina #%s (%s%s) en %s — estado %s',
                    $row['id'] ?? '—',
                    $row['type'] ?? '—',
                    $line,
                    $subject,
                    $row['status'] ?? '—',
                );
            }),
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatPredictions(array $data): string
    {
        $predictions = $data['predictions'] ?? [];
        if ($predictions === []) {
            return 'No hay equipos con historial suficiente para predecir fallas con ese filtro.';
        }

        $lines = [];
        foreach (array_slice($predictions, 0, 10) as $prediction) {
            $lines[] = sprintf(
                '- %s · riesgo %s · %.0f %% de falla en %d días · modo probable: %s',
                $prediction['tag'] ?? 'Activo #'.($prediction['asset_id'] ?? '—'),
                $prediction['risk_level'] ?? '—',
                (float) ($prediction['probability'] ?? 0) * 100,
                (int) ($data['horizon_days'] ?? 0),
                $prediction['top_failure_mode']['name'] ?? 'sin determinar',
            );
            foreach (array_slice($prediction['why'] ?? [], 0, 2) as $why) {
                $lines[] = '  · '.$why;
            }
        }

        $summary = $data['risk_summary'] ?? [];

        return sprintf(
            "Riesgo de falla a %d días (corte %s, %d equipos evaluados: %d crítico, %d alto, %d medio, %d bajo):\n%s",
            (int) ($data['horizon_days'] ?? 0),
            (string) ($data['as_of'] ?? '—'),
            (int) ($data['evaluated_assets'] ?? 0),
            (int) ($summary['critical'] ?? 0),
            (int) ($summary['high'] ?? 0),
            (int) ($summary['medium'] ?? 0),
            (int) ($summary['low'] ?? 0),
            implode("\n", $lines),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatClientDemand(array $data): string
    {
        $predictions = $data['predictions'] ?? [];
        if ($predictions === []) {
            $notes = $data['notes'] ?? null;
            if (is_array($notes) && isset($notes[0]) && is_string($notes[0])) {
                return $notes[0];
            }
            if (is_string($notes) && $notes !== '') {
                return $notes;
            }

            return 'No hay historial suficiente de manufactura o suministro con cliente para estimar demanda.';
        }

        $lines = [];
        foreach (array_slice($predictions, 0, 10) as $prediction) {
            $lines[] = sprintf(
                '- %s · %s (%s) · score %.2f · %d rutinas · última %s',
                $prediction['client_name'] ?? 'Cliente #'.($prediction['client_id'] ?? '—'),
                $prediction['routine_type_name'] ?? 'tipo',
                $prediction['service_line_label'] ?? ($prediction['service_line'] ?? '—'),
                (float) ($prediction['score'] ?? 0),
                (int) ($prediction['routines_in_lookback'] ?? 0),
                $prediction['last_routine_at'] ?? '—',
            );
        }

        $linesFilter = is_array($data['service_lines'] ?? null)
            ? implode(', ', $data['service_lines'])
            : 'manufactura/suministro';

        return sprintf(
            "Demanda estimada a clientes (%s, horizonte %d días):\n%s",
            $linesFilter,
            (int) ($data['horizon_days'] ?? 30),
            implode("\n", $lines),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatEquipmentHealth(array $data): string
    {
        $asset = $data['asset'] ?? [];
        $reliability = $data['reliability'] ?? [];
        $prediction = $data['prediction'] ?? [];

        $lines = [sprintf(
            '%s (%s%s) · corte %s',
            $asset['tag'] ?? 'Equipo',
            $asset['equipment_class'] ?? 'sin clase',
            isset($asset['manufacturer']) ? ', '.$asset['manufacturer'].' '.($asset['model'] ?? '') : '',
            (string) ($data['as_of'] ?? '—'),
        )];

        $lines[] = sprintf(
            '- Riesgo %s · %.0f %% de falla en %d días · modo probable: %s',
            $prediction['risk_level'] ?? '—',
            (float) ($prediction['probability'] ?? 0) * 100,
            (int) ($prediction['horizon_days'] ?? 0),
            $prediction['top_failure_mode']['name'] ?? 'sin determinar',
        );
        $lines[] = sprintf(
            '- Horómetro %s h · disponibilidad 7d %s · MTBF %s h · MTTR %s h',
            $reliability['hour_meter'] ?? '—',
            isset($reliability['availability_7d']) ? round((float) $reliability['availability_7d'] * 100, 1).' %' : '—',
            $reliability['mtbf_hours'] ?? '—',
            $reliability['mttr_hours'] ?? '—',
        );
        $lines[] = sprintf(
            '- Fallas 30d: %s · 90d: %s · cumplimiento de preventivo 90d: %s',
            $reliability['failures_30d'] ?? 0,
            $reliability['failures_90d'] ?? 0,
            isset($reliability['pm_compliance_90d'])
                ? round((float) $reliability['pm_compliance_90d'] * 100).' %'
                : 'sin plan registrado',
        );

        foreach (array_slice($prediction['drivers'] ?? [], 0, 4) as $driver) {
            $lines[] = '  · '.$driver['evidence'];
        }

        return implode("\n", $lines);
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

        return 'Resumen basado en herramientas ('.implode(', ', array_column($toolTrace, 'name'))."):\n- "
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
