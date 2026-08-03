# Tour de producto (voz precargada)

El tour reproduce archivos en `public/audio/onboarding/` (`.mp3` con ElevenLabs o `.m4a` con voz local en macOS). La aplicación **no** llama a ElevenLabs en runtime.

## Regenerar audio (solo al cambiar el guion)

1. Edita `resources/onboarding/narration.es.json`.
2. En `.env` (gitignored), define tu clave de [ElevenLabs](https://elevenlabs.io/):

```bash
ELEVENLABS_API_KEY=tu_clave
# opcional: otra voz / modelo
# ELEVENLABS_VOICE_ID=pFZP5JQG7iQjIQuC4Bku
# ELEVENLABS_MODEL_ID=eleven_multilingual_v2
```

El script lee automáticamente variables `ELEVENLABS_*` desde `.env`.

3. Genera **todos** los MP3 con ElevenLabs (sobrescribe archivos existentes):

```bash
npm run onboarding:audio:elevenlabs
```

Alternativa manual: `ONBOARDING_AUDIO_FORCE=1 npm run onboarding:audio` (usa ElevenLabs si hay clave; en macOS sin clave cae en `say`).

4. Verifica `public/audio/onboarding/*.mp3` y commitea junto con el JSON.

Variables opcionales:

| Variable | Uso |
|----------|-----|
| `ELEVENLABS_API_KEY` | Obligatoria en Linux/CI; en macOS opcional (`say` + `afconvert`) |
| `ELEVENLABS_VOICE_ID` | Voz (default: voz multilingüe en el script) |
| `ELEVENLABS_MODEL_ID` | Default `eleven_multilingual_v2` |
| `ONBOARDING_AUDIO_FORCE` | `1` para sobrescribir audio existente |
| `ONBOARDING_AUDIO_PROVIDER` | `elevenlabs` (script `onboarding:audio:elevenlabs`) o `local` (`say` en macOS) |

## Uso en la app

- Primera visita al **Inicio**: invitación a iniciar el tour.
- Botón **Ver tour guiado** en el dashboard.
- El tour filtra pasos según `company.modules`, si eres administrador de plataforma (workflows, tenants, selector de empresa) y si tienes capacidad de IA (`canUseAi`: FAB del asistente). Clave `localStorage`: `phoenix_product_tour_v6_completed`.
- Los pasos de módulo abren la página real y resaltan el contenido con recorte (cutout); el menú lateral se destaca en los pasos de navegación.
- Incluye Integraciones, Configuración (tema, móvil/PIN, IA) y, cuando aplica, el FAB del asistente.
- Sin `ELEVENLABS_API_KEY` válida en macOS, el script usa `say` y escribe `.m4a` (o `.mp3` si tienes `ffmpeg`). El reproductor intenta `.mp3` y luego `.m4a`; si faltan ambos, usa síntesis de voz del navegador.

## Seguridad

Si una clave de ElevenLabs se expuso en chat, logs o commits, **rótala** en el panel de ElevenLabs.
