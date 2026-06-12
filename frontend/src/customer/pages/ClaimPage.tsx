import { useEffect, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { customerApi } from '@shared/clients'
import { customerToken } from '@shared/auth'
import { isApiError } from '@shared/api'
import { Banner, Logo, Screen, Spinner } from '@shared/ui'

/** Landingspagina na de e-mailverificatie: wisselt de claim-code in voor een device-token. */
export default function ClaimPage() {
    const [params] = useSearchParams()
    const navigate = useNavigate()
    const [error, setError] = useState<string | null>(null)
    const done = useRef(false)

    useEffect(() => {
        if (done.current) return
        done.current = true

        const code = params.get('code')
        if (!code) {
            setError('Geen geldige code in de link.')
            return
        }

        customerApi
            .claim(code)
            .then(({ device_token }) => {
                customerToken.set(device_token)
                navigate('/', { replace: true })
            })
            .catch((e) => setError(isApiError(e) ? e.message : 'De link is ongeldig of verlopen.'))
    }, [params, navigate])

    return (
        <Screen>
            <div className="mb-8 mt-4">
                <Logo />
            </div>
            <div className="card flex flex-col items-center gap-3 p-8 text-center">
                {error ? (
                    <>
                        <div className="text-4xl">⚠️</div>
                        <Banner kind="error">{error}</Banner>
                        <a className="btn-primary mt-2 w-full" href="/">
                            Opnieuw beginnen
                        </a>
                    </>
                ) : (
                    <>
                        <Spinner />
                        <p className="text-sm text-muted">Je toestel wordt gekoppeld…</p>
                    </>
                )}
            </div>
        </Screen>
    )
}
