import { useCallback, useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import type { Card, CardProduct, Customer, Drink, Staff } from '@shared/types'
import { staffApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import { Banner, Logo, Screen, Spinner } from '@shared/ui'
import CameraScanner from '../components/CameraScanner'
import HardwareScanInput from '../components/HardwareScanInput'
import NewCardFlow from '../components/NewCardFlow'
import DrinkPicker from '../components/DrinkPicker'
import { extractNonce } from '../scan'

type View =
    | { kind: 'scan' }
    | { kind: 'identify'; customer: Customer; products: CardProduct[] }
    | { kind: 'redeemed'; card: Card; drink: Drink | null }
    | { kind: 'needs_activation'; card: Card }
    | { kind: 'done'; card: Card; title: string }
    | { kind: 'error'; message: string }

export default function ScanPage({ staff, onSignOut }: { staff: Staff | null; onSignOut: () => void }) {
    const [view, setView] = useState<View>({ kind: 'scan' })
    const [camera, setCamera] = useState(true)
    const [manual, setManual] = useState('')
    const [drinkType, setDrinkType] = useState('cappuccino')
    const [drinkSize, setDrinkSize] = useState('medium')
    const busy = useRef(false)

    const { data: drinksData } = useQuery({ queryKey: ['drinks'], queryFn: staffApi.drinks })
    const drinks = drinksData?.data ?? []

    const selectedDrink = useMemo(
        () => drinks.find((d) => d.type === drinkType && d.size === drinkSize),
        [drinks, drinkType, drinkSize],
    )

    const process = useCallback(
        async (raw: string) => {
            if (busy.current) return
            busy.current = true
            try {
                const res = await staffApi.scan(extractNonce(raw), selectedDrink?.id)
                if (res.type === 'identify') {
                    setView({ kind: 'identify', customer: res.customer, products: res.products })
                } else if (res.result === 'redeemed') {
                    setView({ kind: 'redeemed', card: res.card, drink: res.drink })
                } else {
                    setView({ kind: 'needs_activation', card: res.card })
                }
            } catch (e) {
                setView({ kind: 'error', message: isApiError(e) ? e.message : 'Scan mislukt.' })
            } finally {
                busy.current = false
            }
        },
        [selectedDrink],
    )

    const reset = () => {
        setView({ kind: 'scan' })
        setManual('')
    }

    const activate = async (card: Card, method: 'pin' | 'cash') => {
        try {
            const { card: activated } = await staffApi.activateCard(card.id, method)
            setView({ kind: 'done', card: activated, title: 'Kaart geactiveerd' })
        } catch (e) {
            setView({ kind: 'error', message: isApiError(e) ? e.message : 'Activeren mislukt.' })
        }
    }

    return (
        <Screen>
            <header className="mb-5 flex items-center justify-between">
                <Logo subtitle={staff ? `Balie · ${staff.name}` : 'Balie'} />
                <div className="flex items-center gap-1">
                    {staff?.role === 'admin' && (
                        <Link className="btn-ghost px-3 py-2 text-sm" to="/dashboard">
                            Dashboard
                        </Link>
                    )}
                    <button
                        className="btn-ghost px-3 py-2 text-sm"
                        onClick={async () => {
                            await staffApi.logout().catch(() => {})
                            onSignOut()
                        }}
                    >
                        Uitloggen
                    </button>
                </div>
            </header>

            {/* Hardware-scanner luistert alleen tijdens het scannen. */}
            <HardwareScanInput enabled={view.kind === 'scan'} onScan={process} />

            {view.kind === 'scan' && (
                <div className="flex flex-col gap-4">
                    {drinks.length > 0 && (
                        <DrinkPicker
                            drinks={drinks}
                            type={drinkType}
                            size={drinkSize}
                            onType={setDrinkType}
                            onSize={setDrinkSize}
                        />
                    )}

                    <p className="text-sm text-muted">
                        Scan de QR van de klant met de camera of een hardware-scanner.
                    </p>

                    {camera ? <CameraScanner onResult={process} /> : null}

                    <button className="btn-ghost text-sm" onClick={() => setCamera((c) => !c)}>
                        {camera ? 'Camera uit' : 'Camera aan'}
                    </button>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault()
                            if (manual.trim()) process(manual)
                        }}
                        className="flex gap-2"
                    >
                        <input
                            className="input"
                            placeholder="…of plak een code"
                            value={manual}
                            onChange={(e) => setManual(e.target.value)}
                        />
                        <button className="btn-primary px-4">Ga</button>
                    </form>
                </div>
            )}

            {view.kind === 'identify' && (
                <div className="flex flex-col gap-4">
                    <NewCardFlow
                        customer={view.customer}
                        products={view.products}
                        onDone={(card) => setView({ kind: 'done', card, title: 'Kaart aangemaakt' })}
                    />
                    <button className="btn-ghost text-sm" onClick={reset}>
                        Annuleren
                    </button>
                </div>
            )}

            {view.kind === 'redeemed' && (
                <Confirmation
                    emoji="☕"
                    title="Geschonken!"
                    big={`nog ${view.card.cups_remaining}`}
                    sub={
                        view.drink
                            ? `${view.drink.type_label} · ${view.drink.size_label} — van ${view.card.cups_total} koppen`
                            : `van ${view.card.cups_total} koppen`
                    }
                    onNext={reset}
                />
            )}

            {view.kind === 'done' && (
                <Confirmation
                    emoji="✅"
                    title={view.title}
                    big={`${view.card.cups_remaining} / ${view.card.cups_total}`}
                    sub="koppen actief"
                    onNext={reset}
                />
            )}

            {view.kind === 'needs_activation' && (
                <div className="flex flex-col gap-4">
                    <Banner kind="info">Deze kaart moet eerst betaald en geactiveerd worden.</Banner>
                    <div className="text-sm text-muted">Leg de betaling vast:</div>
                    <div className="grid grid-cols-2 gap-2">
                        <button className="btn-primary" onClick={() => activate(view.card, 'pin')}>
                            Pin
                        </button>
                        <button className="btn-primary" onClick={() => activate(view.card, 'cash')}>
                            Contant
                        </button>
                    </div>
                    <button className="btn-ghost text-sm" onClick={reset}>
                        Annuleren
                    </button>
                </div>
            )}

            {view.kind === 'error' && (
                <div className="flex flex-col items-center gap-4 pt-6 text-center">
                    <div className="text-5xl">⚠️</div>
                    <h2 className="text-xl font-bold">Niet gelukt</h2>
                    <Banner kind="error">{view.message}</Banner>
                    <button className="btn-primary w-full" onClick={reset}>
                        Volgende klant
                    </button>
                </div>
            )}

            {busy.current && view.kind === 'scan' && (
                <div className="mt-4 flex justify-center">
                    <Spinner />
                </div>
            )}
        </Screen>
    )
}

function Confirmation({
    emoji,
    title,
    big,
    sub,
    onNext,
}: {
    emoji: string
    title: string
    big: string
    sub: string
    onNext: () => void
}) {
    return (
        <div className="flex flex-col items-center gap-3 pt-8 text-center">
            <div className="text-6xl">{emoji}</div>
            <h2 className="text-2xl font-extrabold">{title}</h2>
            <div className="text-5xl font-extrabold text-caramel">{big}</div>
            <div className="text-muted">{sub}</div>
            <button className="btn-primary mt-6 w-full" onClick={onNext}>
                Volgende klant
            </button>
        </div>
    )
}
