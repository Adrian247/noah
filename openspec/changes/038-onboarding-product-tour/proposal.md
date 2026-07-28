# 038 — Tour de inicio (highlight + voz precargada)

## Problema

Usuarios nuevos en Noah no tienen un recorrido guiado por catálogos, rutinas, diseño y facturación. Un tour solo con texto es fácil de ignorar; la voz mejora retención pero **no** debe invocar ElevenLabs en cada visita (costo y latencia).

## Objetivo

1. Tour interactivo en la app web (post-login): **spotlight** sobre UI + tarjeta de paso.
2. **Narración en español** reproducida desde archivos **MP3 estáticos** en `public/audio/onboarding/`.
3. Guion y audio generados **una vez** con script local `scripts/generate-onboarding-audio.mjs` (ElevenLabs); la app **nunca** llama a la API en runtime.
4. Estado en `localStorage` (`noah_product_tour_v1_completed`); reinicio manual desde Inicio.

## Alcance v1

- ~8 pasos: bienvenida, inicio/dashboard, menú, rutinas, equipos, diseño (formularios), facturación, cierre.
- Navegación automática de ruta por paso cuando haga falta; anclas `data-tour` en shell y páginas clave.
- Controles: Siguiente, Anterior, Omitir, silenciar voz.
- Documentación: `docs/ONBOARDING_TOUR.md` + variables en `.env.example` (solo generación).

## Fuera de alcance

- Persistir “tour visto” en backend por usuario.
- Tour en portal cliente.
- Traducciones distintas de español (nuevos JSON + regenerar audio).

## Seguridad

- `ELEVENLABS_API_KEY` solo en entorno local/CI para el script; **no** commitear claves.
- Rotar clave si se filtró en chat o logs.

## Referencias técnicas

- Pasos: `resources/js/lib/onboarding/tourSteps.ts`
- Guion fuente: `resources/onboarding/narration.es.json`
- UI: `resources/js/components/onboarding/ProductTour.vue`
