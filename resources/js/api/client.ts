const TOKEN_KEY = 'phoenix_token';
const COMPANY_KEY = 'phoenix_company_id';

export function getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
    localStorage.removeItem(TOKEN_KEY);
}

export function getCompanyId(): string | null {
    return localStorage.getItem(COMPANY_KEY);
}

export function setCompanyId(id: number): void {
    localStorage.setItem(COMPANY_KEY, String(id));
}

export function clearCompanyId(): void {
    localStorage.removeItem(COMPANY_KEY);
}

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public body?: unknown,
    ) {
        super(message);
    }
}

export async function api<T>(
    path: string,
    options: RequestInit = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...(options.headers as Record<string, string> | undefined),
    };

    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const companyId = getCompanyId();
    if (companyId && !path.includes('/auth/login')) {
        headers['X-Company-Id'] = companyId;
    }

    const res = await fetch(`/api/v1${path}`, { ...options, headers });

    const text = await res.text();
    let data: unknown = null;
    if (text) {
        const trimmed = text.trim();
        if (trimmed.startsWith('<')) {
            const hint =
                res.status === 404
                    ? 'El servidor devolvió HTML (ruta no encontrada). Revisa la URL de la API.'
                    : 'El servidor devolvió HTML en lugar de JSON. ¿Sesión expirada o error del servidor?';
            throw new ApiError(hint, res.status, { raw: trimmed.slice(0, 200) });
        }
        try {
            data = JSON.parse(text) as unknown;
        } catch {
            throw new ApiError('Respuesta no válida del servidor (JSON corrupto).', res.status, {
                raw: trimmed.slice(0, 200),
            });
        }
    }

    if (!res.ok) {
        const message = formatApiErrorMessage(data, res.statusText);
        throw new ApiError(message, res.status, data);
    }

    return data as T;
}

function formatApiErrorMessage(data: unknown, fallback: string): string {
    if (!data || typeof data !== 'object') {
        return fallback;
    }
    const record = data as { message?: string; errors?: Record<string, string[]> };
    const fieldErrors = record.errors ? Object.values(record.errors).flat().filter(Boolean) : [];
    if (fieldErrors.length > 0) {
        return fieldErrors[0] as string;
    }
    if (typeof record.message === 'string' && record.message.trim() !== '') {
        return record.message;
    }

    return fallback;
}
