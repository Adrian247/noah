const TOKEN_KEY = 'noah_token';
const COMPANY_KEY = 'noah_company_id';

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
    const data = text ? JSON.parse(text) : null;

    if (!res.ok) {
        throw new ApiError(
            data?.message ?? res.statusText,
            res.status,
            data,
        );
    }

    return data as T;
}
