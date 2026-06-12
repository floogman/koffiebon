import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { customerApi } from '@shared/clients'
import { customerToken } from '@shared/auth'
import type { Card } from '@shared/types'
import { Banner, Logo, Screen, Spinner } from '@shared/ui'
import CardCard from '../components/CardCard'
import QrOverlay from '../components/QrOverlay'

export default function HomePage({ onSignOut }: { onSignOut: () => void }) {
    const [overlay, setOverlay] = useState<{ mode: 'identify' | 'redeem'; card?: Card } | null>(null)

    const { data, isLoading, isError } = useQuery({
        queryKey: ['me'],
        queryFn: customerApi.me,
        // Live verversen zodat het saldo daalt zodra de balie scant.
        refetchInterval: overlay ? 3000 : 10000,
    })

    const handle401 = () => {
        // Token ongeldig -> uitloggen.
        customerToken.clear()
        onSignOut()
    }

    if (isLoading) {
        return (
            <Screen>
                <div className="flex flex-1 items-center justify-center">
                    <Spinner />
                </div>
            </Screen>
        )
    }

    if (isError || !data) {
        return (
            <Screen>
                <Banner kind="error">
                    Kon je kaarten niet laden.{' '}
                    <button className="underline" onClick={handle401}>
                        Opnieuw inloggen
                    </button>
                </Banner>
            </Screen>
        )
    }

    const cards = data.cards ?? []

    return (
        <>
            <Screen>
                <header className="mb-6 flex items-center justify-between">
                    <Logo subtitle={data.email} />
                    <button className="btn-ghost px-3 py-2 text-sm" onClick={onSignOut}>
                        Uitloggen
                    </button>
                </header>

                {cards.length === 0 ? (
                    <div className="card flex flex-col items-center gap-4 p-8 text-center">
                        <div className="text-5xl">☕</div>
                        <h1 className="text-xl font-bold">Nog geen kaart</h1>
                        <p className="text-sm text-muted">
                            Toon je QR aan de balie om een koffiekaart te kopen.
                        </p>
                        <button className="btn-primary w-full" onClick={() => setOverlay({ mode: 'identify' })}>
                            Toon QR om een kaart te kopen
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        {cards.map((card) => (
                            <CardCard key={card.id} card={card} onShowQr={(c) => setOverlay({ mode: 'redeem', card: c })} />
                        ))}
                        <button
                            className="btn-ghost mt-2 text-sm"
                            onClick={() => setOverlay({ mode: 'identify' })}
                        >
                            + Nog een kaart kopen
                        </button>
                    </div>
                )}

                <div className="mt-auto pt-8 text-center text-xs text-muted">
                    Kaart kwijt? Log uit en gebruik de herstellink in je e-mail.
                </div>
            </Screen>

            {overlay && (
                <QrOverlay mode={overlay.mode} card={overlay.card} onClose={() => setOverlay(null)} />
            )}
        </>
    )
}
