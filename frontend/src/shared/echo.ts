import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { customerToken } from './auth'

// Pusher-protocol client die Reverb aanstuurt. Globaal beschikbaar maken zoals
// laravel-echo verwacht.
declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

const KEY = import.meta.env.VITE_REVERB_APP_KEY as string | undefined
const HOST = (import.meta.env.VITE_REVERB_HOST as string | undefined) || window.location.hostname
const PORT = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)
const SCHEME = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http'

let echo: Echo<'reverb'> | null = null

/**
 * Lazy singleton-Echo. Geeft `null` terug als Reverb niet geconfigureerd is, zodat
 * de PWA stil terugvalt op polling. De websocket authenticeert privé-kanalen via
 * /api/broadcasting/auth met het device-bearer-token.
 */
export function getEcho(): Echo<'reverb'> | null {
    if (!KEY) return null
    if (echo) return echo

    window.Pusher = Pusher
    echo = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: KEY,
        wsHost: HOST,
        wsPort: PORT,
        wssPort: PORT,
        forceTLS: SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: { Authorization: `Bearer ${customerToken.get() ?? ''}` },
        },
    })
    return echo
}

/** Verbreek de verbinding (bij uitloggen) zodat een volgend toestel/token vers begint. */
export function disconnectEcho(): void {
    echo?.disconnect()
    echo = null
}
