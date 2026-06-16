import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { customerApi } from '@shared/clients'
import DrinkPicker from '@shared/DrinkPicker'
import type { Card } from '@shared/types'
import { Spinner } from '@shared/ui'
import RotatingQr from './RotatingQr'

/**
 * Fullscreen overlay met de roterende QR. Bij het kopen van een kaart (identify)
 * kiest de klant vooraf een vast drankje (type + maat); die keuze reist mee in de
 * QR en komt op de nieuwe kaart te staan. Bij verzilveren toont de QR het vaste
 * drankje van de kaart. Het saldo blijft live verversen op de achtergrond.
 */
export default function QrOverlay({
    mode,
    card,
    onClose,
}: {
    mode: 'identify' | 'redeem'
    card?: Card
    onClose: () => void
}) {
    // Drankenkaart alleen nodig bij het kopen van een kaart.
    const { data: drinksData } = useQuery({
        queryKey: ['pwa-drinks'],
        queryFn: customerApi.drinks,
        enabled: mode === 'identify',
    })
    const drinks = drinksData?.data ?? []

    const [type, setType] = useState<string | null>(null)
    const [size, setSize] = useState<string | null>(null)

    // Standaard de eerste drank voorselecteren zodra de kaart geladen is (keuze is verplicht).
    useEffect(() => {
        if (mode !== 'identify' || drinks.length === 0) return
        setType((t) => t ?? drinks[0].type)
        setSize((s) => s ?? drinks[0].size)
    }, [mode, drinks])

    const selectedLabel = useMemo(() => {
        if (!type || !size) return null
        const typeLabel = drinks.find((d) => d.type === type)?.type_label ?? type
        const sizeLabel = drinks.find((d) => d.size === size)?.size_label ?? size
        return `${typeLabel} · ${sizeLabel}`
    }, [drinks, type, size])

    const ready = mode === 'redeem' || (type !== null && size !== null)

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-cream">
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col overflow-y-auto px-5 py-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold">
                        {mode === 'identify' ? 'Koop een kaart' : card?.product?.name ?? 'Jouw kaart'}
                    </h2>
                    <button className="btn-ghost px-3 py-2 text-2xl leading-none" onClick={onClose} aria-label="Sluiten">
                        ×
                    </button>
                </div>

                <div className="flex flex-1 flex-col items-center justify-center gap-6 py-4">
                    {mode === 'identify' && (
                        <div className="w-full">
                            <DrinkPicker
                                drinks={drinks}
                                type={type ?? ''}
                                size={size ?? ''}
                                onType={setType}
                                onSize={setSize}
                                title="Welke koffie wil je op je kaart?"
                            />
                        </div>
                    )}

                    {mode === 'redeem' && card && (
                        <div className="text-center">
                            <div className="text-5xl font-extrabold">
                                {card.cups_remaining}
                                <span className="text-2xl text-muted"> / {card.cups_total}</span>
                            </div>
                            <div className="text-sm text-muted">koppen over</div>
                        </div>
                    )}

                    {/* Het vaste drankje dat bij deze QR/kaart hoort. */}
                    {(mode === 'identify' ? selectedLabel : card?.preferred_drink_label) && (
                        <div className="flex flex-col items-center gap-1">
                            <span className="text-xs uppercase tracking-wide text-muted">Jouw koffie</span>
                            <span className="rounded-full bg-espresso px-4 py-1.5 text-base font-bold text-cream">
                                ☕ {mode === 'identify' ? selectedLabel : card?.preferred_drink_label}
                            </span>
                        </div>
                    )}

                    {ready ? (
                        <RotatingQr
                            purpose={mode}
                            cardId={card?.id}
                            label={mode === 'identify' ? 'Laat de balie scannen' : 'Laat scannen voor ☕ −1'}
                            preferredType={type ?? undefined}
                            preferredSize={size ?? undefined}
                        />
                    ) : (
                        <div className="flex h-[300px] items-center justify-center">
                            <Spinner />
                        </div>
                    )}

                    <p className="max-w-xs text-center text-sm text-muted">
                        {mode === 'identify'
                            ? 'De balie scant deze code en maakt je kaart aan met deze koffie.'
                            : 'Elke scan haalt één kop van je kaart. De code ververst automatisch.'}
                    </p>
                </div>
            </div>
        </div>
    )
}
