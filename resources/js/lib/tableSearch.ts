const SEARCH_SKIP_KEYS = new Set(['specifications', 'avatar_url', 'password']);

function appendValue(parts: string[], value: unknown, depth: number) {
    if (value === null || value === undefined || depth > 3) {
        return;
    }
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        parts.push(String(value));
        return;
    }
    if (Array.isArray(value)) {
        for (const item of value) {
            appendValue(parts, item, depth + 1);
        }
        return;
    }
    if (typeof value === 'object') {
        for (const [key, nested] of Object.entries(value as Record<string, unknown>)) {
            if (SEARCH_SKIP_KEYS.has(key)) {
                continue;
            }
            appendValue(parts, nested, depth + 1);
        }
    }
}

/** Texto plano para filtrar filas sin definir columnas de búsqueda. */
export function defaultRowSearchText(row: unknown): string {
    const parts: string[] = [];
    appendValue(parts, row, 0);
    return parts.join(' ').toLowerCase();
}

export function rowMatchesSearch(row: unknown, query: string, custom?: (row: unknown) => string): boolean {
    const q = query.trim().toLowerCase();
    if (!q) {
        return true;
    }
    const haystack = (custom ? custom(row) : defaultRowSearchText(row)).toLowerCase();
    return haystack.includes(q);
}
