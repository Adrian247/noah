#!/usr/bin/env node
/**
 * Genera MP3 del tour desde narration.es.json vía ElevenLabs (una sola vez por cambio de guion).
 *
 * Uso:
 *   ELEVENLABS_API_KEY=... npm run onboarding:audio
 *
 * No ejecutar en runtime de la app.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const narrationPath = path.join(root, 'resources/onboarding/narration.es.json');
const outDir = path.join(root, 'public/audio/onboarding');

function loadDotEnv() {
    const envPath = path.join(root, '.env');
    if (!fs.existsSync(envPath)) {
        return;
    }
    for (const rawLine of fs.readFileSync(envPath, 'utf8').split('\n')) {
        const line = rawLine.trim();
        if (!line || line.startsWith('#')) {
            continue;
        }
        const eq = line.indexOf('=');
        if (eq === -1) {
            continue;
        }
        const key = line.slice(0, eq).trim();
        if (!key.startsWith('ELEVENLABS_')) {
            continue;
        }
        let val = line.slice(eq + 1).trim();
        if (
            (val.startsWith('"') && val.endsWith('"'))
            || (val.startsWith("'") && val.endsWith("'"))
        ) {
            val = val.slice(1, -1);
        }
        if (process.env[key] === undefined) {
            process.env[key] = val;
        }
    }
}

loadDotEnv();

const apiKey = process.env.ELEVENLABS_API_KEY;
const voiceId = process.env.ELEVENLABS_VOICE_ID ?? 'pFZP5JQG7iQjIQuC4Bku';
const modelId = process.env.ELEVENLABS_MODEL_ID ?? 'eleven_multilingual_v2';

if (!apiKey) {
    console.error('Define ELEVENLABS_API_KEY en el entorno (no la commitees).');
    process.exit(1);
}

const narration = JSON.parse(fs.readFileSync(narrationPath, 'utf8'));

fs.mkdirSync(outDir, { recursive: true });

for (const section of narration) {
    const outFile = path.join(outDir, `${section.id}.mp3`);
    if (fs.existsSync(outFile) && process.env.ONBOARDING_AUDIO_FORCE !== '1') {
        console.log(`skip ${section.id} (exists, set ONBOARDING_AUDIO_FORCE=1 to overwrite)`);
        continue;
    }

    console.log(`synthesizing ${section.id}…`);
    const res = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${voiceId}`, {
        method: 'POST',
        headers: {
            'xi-api-key': apiKey,
            'Content-Type': 'application/json',
            Accept: 'audio/mpeg',
        },
        body: JSON.stringify({
            text: section.text,
            model_id: modelId,
            voice_settings: {
                stability: 0.45,
                similarity_boost: 0.75,
                style: 0.2,
                use_speaker_boost: true,
            },
        }),
    });

    if (!res.ok) {
        const errText = await res.text();
        console.error(`ElevenLabs error ${section.id}: ${res.status}`, errText);
        process.exit(1);
    }

    const buffer = Buffer.from(await res.arrayBuffer());
    fs.writeFileSync(outFile, buffer);
    console.log(`wrote ${outFile} (${buffer.length} bytes)`);
}

console.log('Done.');
