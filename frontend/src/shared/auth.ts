// Eenvoudige token-opslag in localStorage. Twee aparte sleutels: de klant-PWA
// gebruikt een passwordless device-token, de balie-app een staff-token.

const CUSTOMER_KEY = 'koffiebon.device_token'
const STAFF_KEY = 'koffiebon.staff_token'

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
