import type { ApiError } from './types'

type TokenGetter = () => string | null

/**
 * Dunne fetch-wrapper rond de Sanctum-API. Voegt het bearer-token toe en gooit
 * een ApiError ({ code, message, status }) bij niet-2xx-responses.
 */
async function request<T>(
    method: string,
    path: string,
    body: unknown,
    getToken: TokenGetter | null,
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
    }
    if (body !== undefined) headers['Content-Type'] = 'application/json'

    const token = getToken?.()
    if (token) headers['Authorization'] = `Bearer ${token}`

    const res = await fetch(`/api${path}`, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    })

    if (res.status === 204) return undefined as T

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
        const err: ApiError = {
            status: res.status,
            code: data.code,
            message: data.message ?? 'Er ging iets mis.',
            errors: data.errors,
        }
        throw err
    }

    return data as T
}

export function makeApi(getToken: TokenGetter | null) {
    return {
        get: <T>(path: string) => request<T>('GET', path, undefined, getToken),
        post: <T>(path: string, body?: unknown) => request<T>('POST', path, body, getToken),
    }
}

export function isApiError(e: unknown): e is ApiError {
    return typeof e === 'object' && e !== null && 'message' in e && 'status' in e
}
