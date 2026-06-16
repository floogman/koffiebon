import type { Card } from '@shared/types'
import { CupProgress } from '@shared/ui'

const STATUS_LABEL: Record<Card['status'], string> = {
    pending: 'Nog te activeren',
    active: 'Actief',
    depleted: 'Leeg',
    expired: 'Verlopen',
    void: 'Geannuleerd',
}

export default function CardCard({ card, onShowQr }: { card: Card; onShowQr: (card: Card) => void }) {
    const canRedeem = card.status === 'active' && card.cups_remaining > 0

    return (
        <div className="card overflow-hidden">
            <div className="bg-espresso px-5 py-4 text-cream">
                <div className="flex items-center justify-between">
                    <span className="font-bold">{card.product?.name ?? 'Koffiekaart'}</span>
                    <span className="rounded-full bg-white/10 px-2 py-0.5 text-xs">
                        {STATUS_LABEL[card.status]}
                    </span>
                </div>
            </div>

            <div className="flex flex-col gap-4 px-5 py-5">
                <div className="flex items-end justify-between">
                    <div>
                        <div className="text-4xl font-extrabold leading-none">
                            {card.cups_remaining}
                            <span className="text-xl font-bold text-muted"> / {card.cups_total}</span>
                        </div>
                        <div className="mt-1 text-sm text-muted">
                            {card.preferred_drink_label ?? 'koppen koffie'}
                        </div>
                    </div>
                    <div className="text-3xl">☕</div>
                </div>

                <CupProgress remaining={card.cups_remaining} total={card.cups_total} />

                <button className="btn-primary w-full" disabled={!canRedeem} onClick={() => onShowQr(card)}>
                    {canRedeem ? 'Toon aan de balie' : 'Geen koppen meer'}
                </button>
            </div>
        </div>
    )
}
