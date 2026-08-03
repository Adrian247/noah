/** Paleta estable para iniciales sin foto (contraste AA con texto blanco). */
const AVATAR_HUES = [220, 258, 198, 338, 24, 158, 42, 278, 312, 185];

export type AvatarTone = {
    background: string;
    color: string;
};

export function avatarToneFromName(name: string): AvatarTone {
    const trimmed = name.trim();
    let hash = 0;
    for (let i = 0; i < trimmed.length; i++) {
        hash = trimmed.charCodeAt(i) + ((hash << 5) - hash);
    }

    const hue = AVATAR_HUES[Math.abs(hash) % AVATAR_HUES.length];

    return {
        background: `hsl(${hue} 46% 36%)`,
        color: '#ffffff',
    };
}

/**
 * Luminancia media 0–255 de una imagen (ignora píxeles muy transparentes).
 */
export function averageImageLuminance(img: HTMLImageElement, sampleSize = 32): number | null {
    const width = Math.max(1, Math.min(sampleSize, img.naturalWidth || sampleSize));
    const height = Math.max(1, Math.min(sampleSize, img.naturalHeight || sampleSize));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return null;
    }

    try {
        ctx.drawImage(img, 0, 0, width, height);
        const { data } = ctx.getImageData(0, 0, width, height);

        let sum = 0;
        let count = 0;

        for (let i = 0; i < data.length; i += 4) {
            const alpha = data[i + 3];
            if (alpha < 40) {
                continue;
            }

            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            sum += 0.2126 * r + 0.7152 * g + 0.0722 * b;
            count++;
        }

        if (count === 0) {
            return null;
        }

        return sum / count;
    } catch {
        return null;
    }
}

/** Fondo del contenedor del logo según luminancia del propio logo. */
export function logoBackdropForLuminance(luminance: number | null): string {
    if (luminance === null) {
        return 'var(--portal-avatar-logo-bg-neutral)';
    }

    if (luminance >= 150) {
        return 'var(--portal-avatar-logo-bg-for-light)';
    }

    if (luminance <= 100) {
        return 'var(--portal-avatar-logo-bg-for-dark)';
    }

    return 'var(--portal-avatar-logo-bg-neutral)';
}
