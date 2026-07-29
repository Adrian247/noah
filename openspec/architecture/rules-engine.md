# Rules Engine — Phoenix

Fase posterior al Workflow v1; diseño anticipado para no codificar reglas por cliente.

## Propósito

Evaluar condiciones sobre datos de ejecución, activo y costos para:

- Requerir aprobación adicional.
- Adjuntar checklist obligatorio.
- Bloquear facturación.
- Disparar notificaciones.

## Modelo (borrador)

```json
{
  "name": "Costo alto correctivo",
  "when": {
    "all": [
      { "field": "routine.type", "eq": "corrective" },
      { "field": "execution.total_cost", "gt": 50000 }
    ]
  },
  "then": [
    { "action": "require_approval", "role": "admin" }
  ]
}
```

## Ejecución

- Tras cambios en ejecución o antes de transición de workflow.
- Motor determinista; sin LLM.

## Relación con Workflow

Workflow puede invocar “evaluar reglas” como nodo; reglas también en metadatos de RoutineType.

## Eventos

- `RuleMatched`, `RuleActionExecuted`
