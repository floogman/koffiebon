import type { Card } from '@shared/types'
import RotatingQr from './RotatingQr'

/**
 * Fullscreen overlay met de roterende QR. Het saldo blijft op de achtergrond
 * live verversen (HomePage polt), dus de klant ziet 12 → 11 terwijl dit open is.
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
    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-cream">
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col px-5 py-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold">
                        {mode === 'identify' ? 'Koop een kaart' : card?.product?.name ?? 'Jouw kaart'}
                    </h2>
                    <button className="btn-ghost px-3 py-2 text-2xl leading-none" onClick={onClose} aria-label="Sluiten">
                        ×
                    </button>
                </div>

                <div className="flex flex-1 flex-col items-center justify-center gap-6">
                    {mode === 'redeem' && card && (
                        <div className="text-center">
                            <div className="text-5xl font-extrabold">
                                {card.cups_remaining}
                                <span className="text-2xl text-muted"> / {card.cups_total}</span>
                            </div>
                            <div className="text-sm text-muted">koppen over</div>
                        </div>
                    )}

                    <RotatingQr
                        purpose={mode}
                        cardId={card?.id}
                        label={mode === 'identify' ? 'Laat de balie scannen' : 'Laat scannen voor ☕ −1'}
                    />

                    <p className="max-w-xs text-center text-sm text-muted">
                        {mode === 'identify'
                            ? 'De balie scant deze code om je nieuwe kaart aan te maken.'
                            : 'Elke scan haalt één kop van je kaart. De code ververst automatisch.'}
                    </p>
                </div>
            </div>
        </div>
    )
}
