# ADR-010 — Flutter para móvil

## Estado

Aceptada (fase 2).

## Contexto

Android e iOS con una sola base de código; offline con SQLite.

## Decisión

Flutter para app de campo; API REST + sync de Noah.

## Alternativas

- React Native — viable; Flutter elegido por consistencia UI y rendimiento en listas/cámara.

## Consecuencias

- Equipo necesita competencia Dart/Flutter o capacitación.
- Formularios dinámicos deben tener renderer compartido conceptualmente con web (mismo esquema JSON).
