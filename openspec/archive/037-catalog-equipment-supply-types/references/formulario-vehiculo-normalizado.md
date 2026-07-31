# Formulario normalizado — tipo de equipo **Vehículo**

## Objetivo

Definir un **único** `FormDefinition` (slug sugerido: `inspeccion-vehiculo-v1`) asociado al tipo de equipo `vehiculo`, usable en tipos de rutina de mantenimiento vehicular. Se **normaliza** a partir de:

- Ficha L200 2018: sistemas relevantes (motorización, frenos disco/tambor, suspensión, capacidades, tanque).
- Formulario demo existente (`revision-mayor-vehiculo-premium`): patrones Phoenix (secciones, `options` con catálogo estado, fotos, campos numéricos).

**No** es copia 1:1 del demo premium ni del MD; es un subconjunto **mantenible** para el tipo Vehículo.

## Principios de normalización

- Campos obligatorios mínimos para validar ejecución (kilometraje + estado de sistemas críticos alineados a la ficha).
- Sección **Plus / agencia premium** del demo → **omitida** en v1 normalizada (o 1–2 campos opcionales genéricos).
- Etiquetas en español México; keys estables en `snake_case`.
- Reutilizar catálogos demo existentes donde aplique (`estado-componente-premium`, `nivel-combustible`, `si-no-servicio`).

## Secciones propuestas

### 1. Recepción e identificación

| key | type | label | req | Nota ficha/demo |
|-----|------|-------|-----|-----------------|
| `kilometraje` | number | Kilometraje (km) | sí | Demo |
| `nivel_combustible` | select | Nivel de combustible | no | Demo; tanque 75 L en specs activo |
| `observaciones_recepcion` | textarea | Observaciones de ingreso | no | Demo |

### 2. Motor y fluidos

| key | type | label | req | Nota |
|-----|------|-------|-----|------|
| `motor_estado` | options | Estado general motor / fugas | sí | Inspirado ficha motorización |
| `aceite_motor` | options | Nivel y estado aceite motor | sí | Demo `aceite` |
| `filtro_aceite_reemplazado` | select | ¿Filtro de aceite reemplazado? | no | Demo + refacción OEM ficha |

### 3. Frenos

| key | type | label | req | Nota |
|-----|------|-------|-----|------|
| `frenos_delanteros` | options | Frenos delanteros (disco) | sí | Ficha: discos ventilados |
| `frenos_traseros` | options | Frenos traseros (tambor) | sí | Ficha: tambor |
| `liquido_frenos` | options | Líquido de frenos / ABS | no | Ficha ABS/EBD |

### 4. Filtros y aire

| key | type | label | req | Nota |
|-----|------|-------|-----|------|
| `filtro_aire` | options | Filtro de aire | sí | Refacción Sakura en catálogo |
| `filtro_habitaculo` | options | Filtro habitáculo | no | Opcional genérico |

### 5. Suspensión y dirección

| key | type | label | req | Nota |
|-----|------|-------|-----|------|
| `suspension` | options | Suspensión / amortiguadores | no | Ficha + refacción GROB |
| `direccion` | options | Dirección hidráulica | no | Ficha |

### 6. Eléctrico básico

| key | type | label | req | Nota |
|-----|------|-------|-----|------|
| `bateria` | options | Batería y bornes | no | Demo reducido |
| `luces` | options | Luces exteriores | no | Demo |

### 7. Cierre (opcional v1)

| key | type | label | req |
|-----|------|-------|-----|
| `comentarios_cierre` | textarea | Comentarios finales | no |
| `foto_evidencia` | photo | Evidencia general (1 foto) | no |

## Implementación en 037

1. Crear `FormDefinition` + publicar `FormVersion` con este esquema (o migrar slug demo a este esquema y actualizar tipo de rutina demo).
2. `equipment_types.vehiculo.default_form_definition_id` → este formulario.
3. Tipo de rutina demo *Revisión mayor vehículo* enlazado a esta versión (sustituye enlace al premium si se depreca el slug antiguo en seed).
4. Tests: actualizar `VehicleDemoFormResponses` → claves del formulario normalizado.

## Fuera de alcance

- Campos por variante gasolina/diésel en el mismo formulario (quedan en `CatalogItem.specifications`).
- OBD, neumáticos detallados, paquete premium extendido.
