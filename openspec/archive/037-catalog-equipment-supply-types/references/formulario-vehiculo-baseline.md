# Baseline — formulario vehículo (seeder actual)

Documento **técnico de referencia** (inventario de campos). En 037 **no** se rediseña el formulario; solo se **enlaza** al tipo de equipo Vehículo. Ver [`formulario-vehiculo-registro.md`](formulario-vehiculo-registro.md).

Fuente: `PhoenixDemoSeeder` → slug `revision-mayor-vehiculo-premium`.

## Secciones y campos (resumen)

| Sección | Campos clave |
|---------|----------------|
| Kilometraje y recepción | `kilometraje` (number, req), `nivel_combustible` (select), `observaciones_recepcion`, `foto_tablero` |
| Frenos | `frenos` (options), `frenos_espesor_pastillas_mm`, `frenos_notas`, `foto_frenos` |
| Filtros | `filtros`, `filtros_cambio_aceite`, `foto_filtro_aceite` |
| Aceite y fluidos | `aceite`, `aceite_viscosidad`, `foto_varilla_aceite` |
| Batería | `bateria`, `bateria_cca`, `foto_bateria` |
| Luces | `luces`, `luces_alineacion`, `foto_luces` |
| Fusibles | `fusibles`, `fusibles_notas`, `foto_caja_fusibles` |
| Revisiones Plus | `plus_suspension`, `plus_transmision`, `plus_neumaticos`, `plus_aire_acondicionado`, `plus_diagnostico_obd`, `foto_neumaticos` |
| Cierre | fotos entrega, comentarios finales (ver seeder completo) |

## Catálogos de opciones usados

- `estado-componente-premium` — operativo / revisión / no aplica / falla  
- `nivel-combustible`  
- `si-no-servicio`  

## Enlace propuesto (037)

- Tipo de equipo **Vehículo** (`code: vehiculo`) → `default_form_definition_id` de este formulario.
- Tipo de rutina *Revisión mayor vehículo (premium)* sin cambio de slug.
