import { useState } from 'react'
import type { Staff } from '@shared/types'
import { staffApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import { Banner, Logo, Screen } from '@shared/ui'

export default function LoginPage({ onLoggedIn }: { onLoggedIn: (token: string, staff: Staff) => void }) {
    const [email, setEmail] = useState('')
    const [password, setPassword] = useState('')
    const [busy, setBusy] = useState(false)
    const [error, setError] = useState<string | null>(null)

    const submit = async (e: React.FormEvent) => {
        e.preventDefault()
        setBusy(true)
        setError(null)
        try {
            const { staff_token, staff } = await staffApi.login(email, password)
            onLoggedIn(staff_token, staff)
        } catch (err) {
            setError(isApiError(err) ? err.message : 'Inloggen mislukt.')
        } finally {
            setBusy(false)
        }
    }

    return (
        <Screen>
            <div className="mb-8 mt-4">
                <Logo subtitle="Balie" />
            </div>
            <form onSubmit={submit} className="card flex flex-col gap-4 p-6">
                <h1 className="text-xl font-bold">Inloggen balie</h1>
                <input
                    className="input"
                    type="email"
                    required
                    placeholder="E-mail"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    autoComplete="username"
                />
                <input
                    className="input"
                    type="password"
                    required
                    placeholder="Wachtwoord"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    autoComplete="current-password"
                />
                {error && <Banner kind="error">{error}</Banner>}
                <button className="btn-primary" disabled={busy}>
                    {busy ? 'Inloggen…' : 'Inloggen'}
                </button>
            </form>
        </Screen>
    )
}
