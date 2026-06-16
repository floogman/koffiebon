import { useState } from 'react'
import { customerApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import { Banner, Logo, Screen } from '@shared/ui'

export default function AuthPage() {
    const [email, setEmail] = useState('')
    const [mode, setMode] = useState<'register' | 'login'>('register')
    const [sent, setSent] = useState(false)
    const [busy, setBusy] = useState(false)
    const [error, setError] = useState<string | null>(null)

    const submit = async (e: React.FormEvent) => {
        e.preventDefault()
        setBusy(true)
        setError(null)
        try {
            if (mode === 'register') await customerApi.register(email)
            else await customerApi.magicLink(email)
            setSent(true)
        } catch (err) {
            setError(isApiError(err) ? err.message : 'Er ging iets mis.')
        } finally {
            setBusy(false)
        }
    }

    return (
        <Screen>
            <div className="mb-8 mt-4">
                <Logo subtitle="Je koffiekaart op zak" />
            </div>

            <div className="card p-6">
                {sent ? (
                    <div className="flex flex-col items-center gap-3 text-center">
                        <div className="text-4xl">📬</div>
                        <h1 className="text-xl font-bold">Check je e-mail</h1>
                        <p className="text-sm text-muted">
                            We hebben een link gestuurd naar <strong>{email}</strong>. Open die op dit
                            toestel om je kaarten te laden.
                        </p>
                        <button className="btn-ghost mt-2 text-sm" onClick={() => setSent(false)}>
                            Ander e-mailadres
                        </button>
                    </div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <h1 className="text-xl font-bold">
                            {mode === 'register' ? 'Aan de slag' : 'Inloggen met je e-mail'}
                        </h1>
                        <p className="text-sm text-muted">
                            {mode === 'register'
                                ? 'Vul je e-mailadres in. Je bevestigt het via een link — geen wachtwoord nodig.'
                                : 'Vul je e-mailadres in. We sturen je een inloglink — geen wachtwoord nodig. Je kaarten en saldi staan veilig op de server.'}
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
                            {busy ? 'Versturen…' : mode === 'register' ? 'Verstuur bevestiging' : 'Verstuur inloglink'}
                        </button>

                        <button
                            type="button"
                            className="text-sm text-caramel underline"
                            onClick={() => setMode(mode === 'register' ? 'login' : 'register')}
                        >
                            {mode === 'register' ? 'Al een account? Inloggen met e-mail' : 'Nieuw hier? Registreren'}
                        </button>
                    </form>
                )}
            </div>
        </Screen>
    )
}
