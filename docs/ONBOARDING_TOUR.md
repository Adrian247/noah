# Tour de producto (voz precargada)

El tour reproduce archivos en `public/audio/onboarding/*.mp3`. La aplicación **no** llama a ElevenLabs en runtime.

## Regenerar audio (solo al cambiar el guion)

1. Edita `resources/onboarding/narration.es.json`.
2. Define la clave en `.env` (gitignored) o exporta en el shell:

```bash
# .env
ELEVENLABS_API_KEY=tu_clave
```

El script `generate-onboarding-audio.mjs` lee automáticamente variables `ELEVENLABS_*` desde `.env`.

```bash
# opcional: export ELEVENLABS_VOICE_ID="..."
# forzar reemplazo: ONBOARDING_AUDIO_FORCE=1
npm run onboarding:audio
```

3. Verifica los MP3 y commitea `public/audio/onboarding/` junto con el JSON.

Variables opcionales:

| Variable | Uso |
|----------|-----|
| `ELEVENLABS_API_KEY` | Obligatoria para el script |
| `ELEVENLABS_VOICE_ID` | Voz (default: voz multilingüe en el script) |
| `ELEVENLABS_MODEL_ID` | Default `eleven_multilingual_v2` |
| `ONBOARDING_AUDIO_FORCE` | `1` para sobrescribir MP3 existentes |

## Uso en la app

- Primera visita al **Inicio**: invitación a iniciar el tour.
- Botón **Ver tour guiado** en el dashboard.
El tour filtra pasos según `company.modules` (misma proyección que el menú). Clave `localStorage`: `noah_product_tour_v2_completed`.

## Seguridad

Si una clave de ElevenLabs se expuso en chat, logs o commits, **rótala** en el panel de ElevenLabs.
