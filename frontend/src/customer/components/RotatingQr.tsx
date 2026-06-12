import { useCallback, useEffect, useRef, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { customerApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import type { QrToken } from '@shared/types'
import { useOnline } from '@shared/useOnline'
import { Spinner } from '@shared/ui'

const REFRESH_MS = 30_000

/**
 * Roterende, eenmalige QR. Vraagt telkens een verse token bij de server, toont een
 * aflopende teller en ververst automatisch (~30s) en bij window-focus. Vereist netwerk.
 */
export default function RotatingQr({
    purpose,
    cardId,
    label,
}: {
    purpose: 'identify' | 'redeem'
    cardId?: number
    label: string
}) {
    const online = useOnline()
    const [token, setToken] = useState<QrToken | null>(null)
    const [error, setError] = useState<string | null>(null)
    const [secondsLeft, setSecondsLeft] = useState(0)
    const timer = useRef<number>()

    const refresh = useCallback(async () => {
        try {
            setError(null)
            const t = await customerApi.issueToken(purpose, cardId)
            setToken(t)
            const secs = Math.max(0, Math.round((new Date(t.expires_at).getTime() - Date.now()) / 1000))
            setSecondsLeft(secs)
        } catch (e) {
            setError(isApiError(e) ? e.message : 'Kon geen QR ophalen.')
        }
    }, [purpose, cardId])

    // Eerste token + auto-refresh interval.
    useEffect(() => {
        if (!online) return
        void refresh()
        timer.current = window.setInterval(refresh, REFRESH_MS)
        return () => window.clearInterval(timer.current)
    }, [refresh, online])

    // Ververs bij focus (terug in de app).
    useEffect(() => {
        const onFocus = () => online && refresh()
        window.addEventListener('focus', onFocus)
        return () => window.removeEventListener('focus', onFocus)
    }, [refresh, online])

    // Aflopende teller.
    useEffect(() => {
        if (secondsLeft <= 0) return
        const id = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000)
        return () => window.clearTimeout(id)
    }, [secondsLeft])

    if (!online) {
        return (
            <div className="flex flex-col items-center gap-3 rounded-2xl bg-black/5 p-8 text-center">
                <div className="text-4xl">📡</div>
                <p className="font-semibold">Ga online om je QR te tonen</p>
                <p className="text-sm text-muted">Je saldo staat veilig op de server.</p>
            </div>
        )
    }

    return (
        <div className="flex flex-col items-center gap-4">
            <div className="relative rounded-2xl bg-white p-5 shadow-sm">
                {token ? (
                    <QRCodeSVG
                        value={token.url}
                        size={232}
                        level="M"
                        fgColor="#1e1410"
                        bgColor="#ffffff"
                        marginSize={0}
                    />
                ) : (
                    <div className="flex h-[232px] w-[232px] items-center justify-center">
                        {error ? <span className="text-sm text-red-700">{error}</span> : <Spinner />}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2 text-sm text-muted">
                <span>{label}</span>
                {token && (
                    <span className="rounded-full bg-espresso px-2 py-0.5 font-mono text-xs text-cream">
                        verloopt in {secondsLeft}s
                    </span>
                )}
            </div>

            <button className="btn-ghost text-sm" onClick={refresh}>
                ↻ Vernieuw QR
            </button>
        </div>
    )
}
