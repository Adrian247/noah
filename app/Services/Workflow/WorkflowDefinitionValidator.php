<?php

namespace App\Services\Workflow;

use Illuminate\Validation\ValidationException;

class WorkflowDefinitionValidator
{
    public const ALLOWED_TRIGGERS = ['execution_submitted', 'approved', 'rejected', 'invoice_issued', 'service_complete'];

    public const ALLOWED_ACTIONS = ['routine_validated'];

    public const ALLOWED_STEP_TYPES = ['human_task', 'end', 'service_task'];

    /**
     * @param  array<string, mixed>  $definition
     */
    public function validate(array $definition): void
    {
        $errors = [];

        $initial = $definition['initial_step'] ?? null;
        $steps = $definition['steps'] ?? null;
        $transitions = $definition['transitions'] ?? null;

        if (! is_string($initial) || $initial === '') {
            $errors['definition.initial_step'] = 'El paso inicial es obligatorio.';
        }

        if (! is_array($steps) || $steps === []) {
            $errors['definition.steps'] = 'Debe definir al menos un paso.';
        }

        if (! is_array($transitions)) {
            $errors['definition.transitions'] = 'Las transiciones deben ser un arreglo.';
            $this->fail($errors);

            return;
        }

        if (is_array($steps)) {
            foreach ($steps as $key => $meta) {
                if (! is_string($key) || $key === '') {
                    $errors['definition.steps'] = 'Cada paso debe tener un identificador válido.';

                    break;
                }
                $type = is_array($meta) ? ($meta['type'] ?? null) : null;
                if (! in_array($type, self::ALLOWED_STEP_TYPES, true)) {
                    $errors["definition.steps.{$key}.type"] = 'Tipo de paso no permitido.';

                    continue;
                }
                if ($type === 'service_task') {
                    $task = is_array($meta) ? ($meta['task'] ?? null) : null;
                    if ($task !== 'send_email') {
                        $errors["definition.steps.{$key}.task"] = 'Tarea de servicio no soportada (use send_email).';
                    }
                }
            }

            if (is_string($initial) && $initial !== '' && ! array_key_exists($initial, $steps)) {
                $errors['definition.initial_step'] = 'El paso inicial no existe en los pasos definidos.';
            }
        }

        $pairs = [];
        foreach ($transitions as $index => $transition) {
            if (! is_array($transition)) {
                $errors["definition.transitions.{$index}"] = 'Transición inválida.';

                continue;
            }

            $from = $transition['from'] ?? null;
            $to = $transition['to'] ?? null;
            $trigger = $transition['trigger'] ?? null;

            if (! is_string($from) || ! is_array($steps) || ! array_key_exists($from, $steps)) {
                $errors["definition.transitions.{$index}.from"] = 'Paso origen inexistente.';
            }
            if (! is_string($to) || ! is_array($steps) || ! array_key_exists($to, $steps)) {
                $errors["definition.transitions.{$index}.to"] = 'Paso destino inexistente.';
            }
            if (! is_string($trigger) || ! in_array($trigger, self::ALLOWED_TRIGGERS, true)) {
                $errors["definition.transitions.{$index}.trigger"] = 'Disparador no permitido.';
            }

            if (is_string($from) && is_string($trigger)) {
                $pairKey = "{$from}|{$trigger}";
                if (isset($pairs[$pairKey])) {
                    $errors["definition.transitions.{$index}.trigger"] = 'Solo puede haber una transición por paso y disparador.';
                }
                $pairs[$pairKey] = true;
            }

            $actions = $transition['actions'] ?? [];
            if ($actions !== [] && ! is_array($actions)) {
                $errors["definition.transitions.{$index}.actions"] = 'Las acciones deben ser un arreglo.';

                continue;
            }
            foreach ($actions as $action) {
                if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
                    $errors["definition.transitions.{$index}.actions"] = 'Acción no permitida.';

                    break;
                }
            }
            if ($trigger !== 'approved' && is_array($actions) && $actions !== []) {
                $errors["definition.transitions.{$index}.actions"] = 'Solo la transición de aprobación puede llevar acciones.';
            }
        }

        if ($errors === [] && is_string($initial) && is_array($steps)) {
            $this->validateRuntimeShape($initial, $steps, $transitions, $errors);
        }

        $this->fail($errors);
    }

    /**
     * @param  array<string, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $transitions
     * @param  array<string, string>  $errors
     */
    private function validateRuntimeShape(string $initial, array $steps, array $transitions, array &$errors): void
    {
        $byFromTrigger = [];
        foreach ($transitions as $t) {
            $from = $t['from'] ?? '';
            $trigger = $t['trigger'] ?? '';
            if ($from !== '' && $trigger !== '') {
                $byFromTrigger["{$from}|{$trigger}"] = $t;
            }
        }

        if (! isset($byFromTrigger["{$initial}|execution_submitted"])) {
            $errors['definition.transitions'] = 'Desde el paso inicial debe existir la transición «execution_submitted».';
        }

        $hasEndStep = false;
        foreach ($steps as $meta) {
            if (is_array($meta) && ($meta['type'] ?? null) === 'end') {
                $hasEndStep = true;

                break;
            }
        }
        if (! $hasEndStep) {
            $errors['definition.steps'] = 'Debe existir al menos un paso de tipo «end».';
        }

        foreach ($steps as $key => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $type = $meta['type'] ?? null;
            if ($type === 'service_task') {
                if (($meta['task'] ?? null) === 'send_email'
                    && ! isset($byFromTrigger["{$key}|".WorkflowRuntime::TRIGGER_SERVICE_COMPLETE])) {
                    $errors['definition.transitions'] = "El paso de email «{$key}» debe tener salida «service_complete».";
                }

                continue;
            }
            if ($type !== 'human_task') {
                continue;
            }
            $role = $meta['assigned_role'] ?? null;
            $isBilling = $key === WorkflowRuntime::STEP_BILLING || $role === 'billing';
            if ($isBilling) {
                if (! isset($byFromTrigger["{$key}|".WorkflowRuntime::TRIGGER_INVOICE_ISSUED])) {
                    $errors['definition.transitions'] = 'El paso de facturación debe permitir la transición «invoice_issued».';
                }

                continue;
            }
            if ($key === $initial) {
                continue;
            }
            if (! isset($byFromTrigger["{$key}|approved"])) {
                $errors['definition.transitions'] = "El paso «{$key}» debe permitir la transición «approved».";

                return;
            }
            if (! isset($byFromTrigger["{$key}|rejected"])) {
                $errors['definition.transitions'] = "El paso «{$key}» debe permitir la transición «rejected».";

                return;
            }
        }

        $billingKey = WorkflowRuntime::STEP_BILLING;
        if (array_key_exists($billingKey, $steps)) {
            foreach ($transitions as $t) {
                if (($t['trigger'] ?? null) === 'approved' && ($t['to'] ?? null) === 'complete') {
                    $errors['definition.transitions'] = 'Con paso de facturación, la aprobación no debe saltar directo al cierre.';

                    return;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function fail(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
