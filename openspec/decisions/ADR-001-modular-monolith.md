# ADR-001 — Monolito modular

## Estado

Aceptada.

## Contexto

Phoenix es greenfield; el equipo es pequeño; se requiere velocidad de entrega sin sacrificar límites de dominio.

## Decisión

Un solo despliegue Laravel con módulos/namespaces por bounded context. Sin microservicios en fases 1–3.

## Alternativas

- Microservicios desde inicio — rechazado por costo operativo.
- Sin modularidad — rechazado por acoplamiento.

## Consecuencias

- Escalado horizontal por réplicas del mismo artefacto.
- Disciplina estricta en fronteras de módulo y eventos.
