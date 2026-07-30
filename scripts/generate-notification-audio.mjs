#!/usr/bin/env node
/**
 * Genera MP3 de notificaciones vía ElevenLabs Sound Effects (una vez por cambio de prompts).
 * Sin ELEVENLABS_API_KEY genera tonos locales con ffmpeg (desarrollo).
 *
 * Uso:
 *   npm run notifications:audio
 *   npm run notifications:audio:elevenlabs
 */
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const manifestPath = path.join(root, 'resources/notifications/sounds.es.json');
const outDir = path.join(root, 'public/audio/notifications');

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
        if (!key.startsWith('ELEVENLABS_') && key !== 'NOTIFICATION_AUDIO_FORCE') {
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

const apiKey = process.env.ELEVENLABS_API_KEY?.trim() || '';
const provider = (process.env.NOTIFICATION_AUDIO_PROVIDER ?? 'auto').toLowerCase();
const force = process.env.NOTIFICATION_AUDIO_FORCE === '1';

if (provider === 'elevenlabs' && !apiKey) {
    console.error(
        'Falta ELEVENLABS_API_KEY. Añádela en .env y ejecuta: npm run notifications:audio:elevenlabs',
    );
    process.exit(1);
}

const useElevenLabs = Boolean(apiKey) && provider !== 'local';
const sounds = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

fs.mkdirSync(outDir, { recursive: true });

function commandExists(cmd) {
    try {
        execSync(`command -v ${cmd}`, { stdio: 'pipe' });
        return true;
    } catch {
        return false;
    }
}

const hasFfmpeg = commandExists('ffmpeg');

const fallbackFreq = {
    success: 880,
    danger: 220,
    warning: 440,
    info: 660,
};

function synthWithFfmpeg(id, outFile) {
    if (!hasFfmpeg) {
        console.error('Instala ffmpeg o define ELEVENLABS_API_KEY para generar audio de notificaciones.');
        process.exit(1);
    }
    const freq = fallbackFreq[id] ?? 520;
    execSync(
        `ffmpeg -y -f lavfi -i "sine=frequency=${freq}:duration=0.35" -af "afade=t=in:st=0:d=0.04,afade=t=out:st=0.22:d=0.13,volume=0.35" -codec:a libmp3lame -qscale:a 6 "${outFile}"`,
        { stdio: 'pipe' },
    );
}

for (const sound of sounds) {
    const outFile = path.join(outDir, `${sound.id}.mp3`);
    if (fs.existsSync(outFile) && !force) {
        console.log(`skip ${sound.id} (exists, NOTIFICATION_AUDIO_FORCE=1 to overwrite)`);
        continue;
    }

    if (!useElevenLabs) {
        console.log(`synthesizing ${sound.id} (ffmpeg fallback)…`);
        synthWithFfmpeg(sound.id, outFile);
        console.log(`wrote ${outFile}`);
        continue;
    }

    console.log(`synthesizing ${sound.id} (ElevenLabs SFX)…`);
    const res = await fetch(
        'https://api.elevenlabs.io/v1/sound-generation?output_format=mp3_44100_128',
        {
            method: 'POST',
            headers: {
                'xi-api-key': apiKey,
                'Content-Type': 'application/json',
                Accept: 'audio/mpeg',
            },
            body: JSON.stringify({
                text: sound.text,
                model_id: 'eleven_text_to_sound_v2',
                duration_seconds: sound.duration_seconds ?? 1.1,
                prompt_influence: sound.prompt_influence ?? 0.4,
            }),
        },
    );

    if (!res.ok) {
        const errText = await res.text();
        console.error(`ElevenLabs error ${sound.id}: ${res.status}`, errText);
        process.exit(1);
    }

    const buffer = Buffer.from(await res.arrayBuffer());
    fs.writeFileSync(outFile, buffer);
    console.log(`wrote ${outFile} (${buffer.length} bytes)`);
}

console.log('Done.');
