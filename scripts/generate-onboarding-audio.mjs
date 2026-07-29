#!/usr/bin/env node
/**
 * Genera MP3 del tour desde narration.es.json vía ElevenLabs (una sola vez por cambio de guion).
 * Sin ELEVENLABS_API_KEY en macOS usa `say` + `afconvert` (voz local de desarrollo).
 *
 * Uso:
 *   npm run onboarding:audio
 *   ONBOARDING_AUDIO_FORCE=1 npm run onboarding:audio
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execSync } from 'node:child_process';
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

const apiKey = process.env.ELEVENLABS_API_KEY?.trim() || '';
const voiceId = process.env.ELEVENLABS_VOICE_ID ?? 'pFZP5JQG7iQjIQuC4Bku';
const modelId = process.env.ELEVENLABS_MODEL_ID ?? 'eleven_multilingual_v2';
const provider = (process.env.ONBOARDING_AUDIO_PROVIDER ?? 'auto').toLowerCase();

if (provider === 'elevenlabs' && !apiKey) {
    console.error(
        'Falta ELEVENLABS_API_KEY. Añádela en .env (ver .env.example) y ejecuta: npm run onboarding:audio:elevenlabs',
    );
    process.exit(1);
}

const useElevenLabs = Boolean(apiKey) && provider !== 'local';

const narration = JSON.parse(fs.readFileSync(narrationPath, 'utf8'));

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

function synthWithMacSay(text, outFileMp3) {
    const base = path.basename(outFileMp3, '.mp3');
    const aiff = path.join(os.tmpdir(), `phoenix-onboarding-${base}.aiff`);
    const textFile = path.join(os.tmpdir(), `phoenix-onboarding-${base}.txt`);
    fs.writeFileSync(textFile, text, 'utf8');
    try {
        execSync(`say -r 178 -o "${aiff}" -f "${textFile}"`, { stdio: 'pipe' });
        if (hasFfmpeg) {
            execSync(`ffmpeg -y -i "${aiff}" -codec:a libmp3lame -qscale:a 5 "${outFileMp3}"`, {
                stdio: 'pipe',
            });
            return outFileMp3;
        }
        const outM4a = outFileMp3.replace(/\.mp3$/i, '.m4a');
        execSync(`afconvert -f m4af -d aac -b 64000 "${aiff}" "${outM4a}"`, { stdio: 'pipe' });
        return outM4a;
    } finally {
        for (const f of [aiff, textFile]) {
            try {
                fs.unlinkSync(f);
            } catch {
                /* ignore */
            }
        }
    }
}

for (const section of narration) {
    const outFile = path.join(outDir, `${section.id}.mp3`);
    const outM4a = path.join(outDir, `${section.id}.m4a`);
    const hasAudio =
        fs.existsSync(outFile) || fs.existsSync(outM4a);
    if (hasAudio && process.env.ONBOARDING_AUDIO_FORCE !== '1') {
        console.log(`skip ${section.id} (exists, set ONBOARDING_AUDIO_FORCE=1 to overwrite)`);
        continue;
    }

    if (!useElevenLabs) {
        if (process.platform !== 'darwin') {
            console.error(
                'Define ELEVENLABS_API_KEY o usa macOS con voz local (`say`). Para forzar ElevenLabs: npm run onboarding:audio:elevenlabs',
            );
            process.exit(1);
        }
        console.log(`synthesizing ${section.id} (macOS say)…`);
        const written = synthWithMacSay(section.text, outFile);
        console.log(`wrote ${written}`);
        continue;
    }

    console.log(`synthesizing ${section.id} (ElevenLabs)…`);
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
    try {
        if (fs.existsSync(outM4a)) {
            fs.unlinkSync(outM4a);
        }
    } catch {
        /* ignore */
    }
    console.log(`wrote ${outFile} (${buffer.length} bytes)`);
}

console.log('Done.');
