// Eenvoudige token-opslag in localStorage. Twee aparte sleutels: de klant-PWA
// gebruikt een passwordless device-token, de balie-app een staff-token.

import type { Staff } from './types'

const CUSTOMER_KEY = 'koffiebon.device_token'
const STAFF_KEY = 'koffiebon.staff_token'
const STAFF_USER_KEY = 'koffiebon.staff_user'

export const customerToken = {
    get: () => localStorage.getItem(CUSTOMER_KEY),
    set: (t: string) => localStorage.setItem(CUSTOMER_KEY, t),
    clear: () => localStorage.removeItem(CUSTOMER_KEY),
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
