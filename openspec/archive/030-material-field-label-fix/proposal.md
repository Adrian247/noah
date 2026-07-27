# 030 — Material inputs: etiqueta flotante

## Objetivo

Evitar que la **etiqueta** del campo Material se superponga al **valor** cuando el input no tiene foco.

## Causa

- `MaterialField` solo aplica `material-field--filled` si `modelValue.length > 0`; valores numéricos u otros tipos rompen la detección.
- `MaterialSelect` forzaba `material-field--filled` siempre.

## Solución

- `hasValue`: `String(modelValue ?? '').trim().length > 0`
- Tipos `modelValue`: `string | number`
- Select: `filled` solo con valor no vacío.
- CSS: padding superior mínimo en input cuando hay valor (refuerzo opcional).

## Criterios de aceptación

- Campos con valor guardado muestran etiqueta arriba sin foco (login, diseñadores, modales, catálogos).
- Select con opción placeholder vacía comporta etiqueta como vacío.

## Alcance

- `MaterialField.vue`, `MaterialSelect.vue`, revisión visual en tema claro/oscuro.
