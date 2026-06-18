// Eenvoudige token-opslag in localStorage. Twee aparte sleutels: de klant-PWA
// gebruikt een passwordless device-token, de balie-app een staff-token.

import type { Staff } from './types'

const CUSTOMER_KEY = 'koffiebon.device_token'
const STAFF_KEY = 'koffiebon.staff_token'
const STAFF_USER_KEY = 'koffiebon.staff_user'
const PENDING_LOGIN_KEY = 'koffiebon.pending_login'

export const customerToken = {
    get: () => localStorage.getItem(CUSTOMER_KEY),
    set: (t: string) => localStorage.setItem(CUSTOMER_KEY, t),
    clear: () => localStorage.removeItem(CUSTOMER_KEY),
}

// Cross-device login: de PWA genereert een geheim, bewaart het lokaal, en stuurt alleen
// sha256(geheim) naar de server. Na bevestiging wisselt ze het geheim in voor een token.
export type PendingLogin = {
    secret: string
    channelHash: string
    email: string
    expiresAt: number // epoch ms
}

export const pendingLogin = {
    get(): PendingLogin | null {
        const raw = localStorage.getItem(PENDING_LOGIN_KEY)
        if (!raw) return null
        try {
            const p = JSON.parse(raw) as PendingLogin
            if (typeof p.expiresAt !== 'number' || Date.now() > p.expiresAt) {
                localStorage.removeItem(PENDING_LOGIN_KEY)
                return null
            }
            return p
        } catch {
            localStorage.removeItem(PENDING_LOGIN_KEY)
            return null
        }
    },
    set: (p: PendingLogin) => localStorage.setItem(PENDING_LOGIN_KEY, JSON.stringify(p)),
    clear: () => localStorage.removeItem(PENDING_LOGIN_KEY),
}

const toHex = (buf: ArrayBuffer | Uint8Array): string =>
    Array.from(new Uint8Array(buf))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('')

/**
 * Genereer een nieuw 256-bit geheim en de bijbehorende kanaalhash. De server kent het
 * geheim nooit; sha256(geheim) is zowel de kanaalnaam als de claim-sleutel (preimage-veilig).
 */
export async function newLoginSecret(): Promise<{ secret: string; channelHash: string }> {
    const bytes = new Uint8Array(32)
    crypto.getRandomValues(bytes)
    const secret = toHex(bytes)
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(secret))
    return { secret, channelHash: toHex(digest) }
}

export const staffToken = {
    get: () => localStorage.getItem(STAFF_KEY),
    set: (t: string) => localStorage.setItem(STAFF_KEY, t),
    clear: () => localStorage.removeItem(STAFF_KEY),
}

// Naast het token bewaren we de staff-gegevens (incl. rol), zodat de balie-app na
// navigeren (bv. terug vanaf het dashboard) of een refresh de rol nog kent.
export const staffUser = {
    get: (): Staff | null => {
        const raw = localStorage.getItem(STAFF_USER_KEY)
        if (!raw) return null
        try {
            return JSON.parse(raw) as Staff
        } catch {
            return null
        }
    },
    set: (s: Staff) => localStorage.setItem(STAFF_USER_KEY, JSON.stringify(s)),
    clear: () => localStorage.removeItem(STAFF_USER_KEY),
}
