import { useEffect, useState } from 'react'
import { customerApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import { customerToken, newLoginSecret, pendingLogin } from '@shared/auth'
import { subscribeLoginConfirmed } from '@shared/echo'
import { Banner, Logo, Screen, Spinner } from '@shared/ui'

// Moet ruwweg overeenkomen met config('koffiebon.login_session_minutes') op de server.
const LOGIN_TTL_MS = 30 * 60 * 1000

export default function AuthPage({ onAuthenticated }: { onAuthenticated: (token: string) => void }) {
    const [phase, setPhase] = useState<'form' | 'waiting'>(() => (pendingLogin.get() ? 'waiting' : 'form'))
    const [email, setEmail] = useState(() => pendingLogin.get()?.email ?? '')
    const [busy, setBusy] = useState(false)
    const [error, setError] = useState<string | null>(null)

    // Wachtscherm: abonneer op het login-kanaal én pol als fallback. Beide proberen het
    // geheim in te wisselen; de eerste die slaagt logt in. Hervat automatisch na een refresh.
    useEffect(() => {
        if (phase !== 'waiting') return
        const pending = pendingLogin.get()
        if (!pending) {
            setPhase('form')
            return
        }

        let stopped = false

        const tryClaim = async () => {
            if (stopped) return
            if (!pendingLogin.get()) {
                stopped = true
                setError('De inlogpoging is verlopen. Probeer het opnieuw.')
                setPhase('form')
                return
            }
            try {
                const { device_token } = await customerApi.claim(pending.secret)
                if (stopped) return
                stopped = true
                pendingLogin.clear()
                customerToken.set(device_token)
                onAuthenticated(device_token)
            } catch (e) {
                if (!isApiError(e)) return
                if (e.code === 'login_pending') return // nog niet bevestigd: blijf wachten
                if (e.code === 'login_expired' || e.code === 'login_invalid') {
                    stopped = true
                    pendingLogin.clear()
                    setError('De inlogpoging is verlopen. Probeer het opnieuw.')
                    setPhase('form')
                }
                // login_consumed of overig: stil negeren (al ingelogd in een ander tabblad).
            }
        }

        const unsub = subscribeLoginConfirmed(pending.channelHash, () => void tryClaim())
        void tryClaim() // direct (vangt een al-bevestigde sessie bij hervatten)
        const interval = window.setInterval(() => void tryClaim(), 3000)

        return () => {
            stopped = true
            window.clearInterval(interval)
            unsub()
        }
    }, [phase, onAuthenticated])

    const submit = async (e: React.FormEvent) => {
        e.preventDefault()
        setBusy(true)
        setError(null)
        try {
            const { secret, channelHash } = await newLoginSecret()
            await customerApi.loginRequest(email, channelHash)
            pendingLogin.set({ secret, channelHash, email, expiresAt: Date.now() + LOGIN_TTL_MS })
            setPhase('waiting')
        } catch (err) {
            setError(isApiError(err) ? err.message : 'Er ging iets mis.')
        } finally {
            setBusy(false)
        }
    }

    const cancel = () => {
        pendingLogin.clear()
        setPhase('form')
        setError(null)
    }

    return (
        <Screen>
            <div className="mb-8 mt-4">
                <Logo subtitle="Je koffiekaart op zak" />
            </div>

            <div className="card p-6">
                {phase === 'waiting' ? (
                    <div className="flex flex-col items-center gap-3 text-center">
                        <div className="text-4xl">📬</div>
                        <h1 className="text-xl font-bold">Check je e-mail</h1>
                        <p className="text-sm text-muted">
                            We hebben een inloglink gestuurd naar <strong>{email}</strong>. Klik die —
                            op welk toestel dan ook. <strong>Je hoeft dit scherm niet te verlaten</strong>;
                            zodra je bevestigt, logt de app zichzelf hier in.
                        </p>
                        <div className="mt-2 flex items-center gap-2 text-sm text-muted">
                            <Spinner /> <span>Wachten op bevestiging…</span>
                        </div>
                        <button className="btn-ghost mt-2 text-sm" onClick={cancel}>
                            Ander e-mailadres
                        </button>
                    </div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <h1 className="text-xl font-bold">Inloggen met je e-mail</h1>
                        <p className="text-sm text-muted">
                            Vul je e-mailadres in. We sturen je een inloglink — geen wachtwoord nodig.
                            Je kaarten en saldi staan veilig op de server.
                        </p>

                        <input
                            className="input"
                            type="email"
                            required
                            placeholder="jij@voorbeeld.nl"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            autoComplete="email"
                            inputMode="email"
                        />

                        {error && <Banner kind="error">{error}</Banner>}

                        <button className="btn-primary" disabled={busy}>
                            {busy ? 'Versturen…' : 'Verstuur inloglink'}
                        </button>
                    </form>
                )}
            </div>
        </Screen>
    )
}
