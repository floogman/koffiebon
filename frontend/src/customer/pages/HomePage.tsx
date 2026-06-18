import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { customerApi } from '@shared/clients'
import { customerToken } from '@shared/auth'
import { getEcho } from '@shared/echo'
import type { Card } from '@shared/types'
import { Banner, Logo, Screen, Spinner } from '@shared/ui'
import CardCard from '../components/CardCard'
import QrOverlay from '../components/QrOverlay'

type CardUpdate = { action: 'redeemed' | 'activated' | 'issued'; card: Card }

function flashFor({ action, card }: CardUpdate): string {
    const n = card.cups_remaining
    const koppen = `${n} ${n === 1 ? 'kop' : 'koppen'}`
    switch (action) {
        case 'redeemed':
            return n === 0 ? '☕ Geschonken — je kaart is nu leeg' : `☕ Geschonken — nog ${koppen}`
        case 'activated':
            return `✅ Kaart geactiveerd — ${koppen} klaar`
        case 'issued':
            return `🎉 Nieuwe kaart — ${koppen}`
    }
}

type OverlayState = { mode: 'identify' | 'redeem'; cardId?: number; done?: CardUpdate }

export default function HomePage({ onSignOut }: { onSignOut: () => void }) {
    const [overlay, setOverlay] = useState<OverlayState | null>(null)
    const [flash, setFlash] = useState<string | null>(null)
    const queryClient = useQueryClient()

    // Spiegelt de actuele overlay zodat de (niet opnieuw abonnerende) Reverb-listener
    // synchroon kan bepalen of de scan bij het open scherm hoort.
    const overlayRef = useRef<OverlayState | null>(overlay)
    useEffect(() => {
        overlayRef.current = overlay
    }, [overlay])

    const { data, isLoading, isError } = useQuery({
        queryKey: ['me'],
        queryFn: customerApi.me,
        // Reverb duwt updates direct; dit polt nog als veilig vangnet (of als Reverb uit staat).
        refetchInterval: overlay ? 5000 : 20000,
    })

    const customerId = data?.id

    // Live kaart-updates via Reverb: zodra de balie scant, ververst het saldo direct
    // en verschijnt er een bevestiging. Valt stil terug op polling als Reverb uit staat.
    useEffect(() => {
        if (!customerId) return
        const echo = getEcho()
        if (!echo) return

        const channel = `Customer.${customerId}`
        echo.private(channel).listen('.card.updated', (e: CardUpdate) => {
            queryClient.invalidateQueries({ queryKey: ['me'] })

            // Hoort de scan bij het open QR-scherm? Dan de QR vervangen door een
            // succesresultaat (blijft staan tot de klant sluit). Anders een korte toast.
            const o = overlayRef.current
            const matchesOpenQr =
                (e.action === 'issued' && o?.mode === 'identify') ||
                (e.action === 'redeemed' && o?.mode === 'redeem' && o?.cardId === e.card.id)

            if (matchesOpenQr) {
                setOverlay((cur) => (cur ? { ...cur, done: e } : cur))
            } else {
                setFlash(flashFor(e))
            }
        })

        return () => {
            echo.leave(channel)
        }
    }, [customerId, queryClient])

    // Bevestiging vanzelf laten verdwijnen.
    useEffect(() => {
        if (!flash) return
        const id = window.setTimeout(() => setFlash(null), 4000)
        return () => window.clearTimeout(id)
    }, [flash])

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
    // Verse kaart uit de query, zodat het saldo in de overlay live meeloopt met de refetch.
    const overlayCard = overlay?.cardId ? cards.find((c) => c.id === overlay.cardId) : undefined

    return (
        <>
            {flash && (
                <div className="fixed inset-x-0 top-4 z-[60] flex justify-center px-4">
                    <div className="rounded-full bg-espresso px-5 py-2.5 text-center text-sm font-semibold text-cream shadow-lg">
                        {flash}
                    </div>
                </div>
            )}

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
                            <CardCard key={card.id} card={card} onShowQr={(c) => setOverlay({ mode: 'redeem', cardId: c.id })} />
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
                    Op een ander toestel? Log uit en log opnieuw in met je e-mailadres — je kaarten staan veilig op de server.
                </div>
            </Screen>

            {overlay && (
                <QrOverlay
                    mode={overlay.mode}
                    card={overlayCard}
                    done={overlay.done}
                    onClose={() => setOverlay(null)}
                />
            )}
        </>
    )
}
