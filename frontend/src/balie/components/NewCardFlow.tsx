import { useState } from 'react'
import type { Card, CardProduct, Customer } from '@shared/types'
import { euro, staffApi } from '@shared/clients'
import { isApiError } from '@shared/api'
import { Banner } from '@shared/ui'

type Method = 'pin' | 'cash'

/** Identify-resultaat: kies product + betaalmethode en maak/activeer de kaart. */
export default function NewCardFlow({
    customer,
    products,
    onDone,
}: {
    customer: Customer
    products: CardProduct[]
    onDone: (card: Card) => void
}) {
    const [productId, setProductId] = useState<number | null>(products[0]?.id ?? null)
    const [method, setMethod] = useState<Method>('pin')
    const [busy, setBusy] = useState(false)
    const [error, setError] = useState<string | null>(null)

    const product = products.find((p) => p.id === productId)
    const priceCents = product ? product.cups_paid * product.price_per_cup_cents : 0

    const confirm = async () => {
        if (!productId) return
        setBusy(true)
        setError(null)
        try {
            const { card } = await staffApi.createCard(customer.id, productId, method)
            onDone(card)
        } catch (e) {
            setError(isApiError(e) ? e.message : 'Kon de kaart niet aanmaken.')
        } finally {
            setBusy(false)
        }
    }

    return (
        <div className="flex flex-col gap-5">
            <div>
                <div className="text-sm text-muted">Nieuwe kaart voor</div>
                <div className="text-lg font-bold">{customer.email}</div>
                {!customer.email_verified && (
                    <Banner kind="error">Deze klant heeft nog geen geverifieerd e-mailadres.</Banner>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <div className="text-sm font-semibold text-muted">Product</div>
                {products.map((p) => (
                    <button
                        key={p.id}
                        onClick={() => setProductId(p.id)}
                        className={`flex items-center justify-between rounded-xl border px-4 py-3 text-left transition ${
                            productId === p.id ? 'border-caramel bg-caramel/10' : 'border-black/10 bg-white'
                        }`}
                    >
                        <div>
                            <div className="font-bold">{p.name}</div>
                            <div className="text-xs text-muted">
                                {p.cups_total} koppen · {p.gift_cups ?? p.cups_total - p.cups_paid} cadeau
                            </div>
                        </div>
                        <div className="font-bold">{euro(p.cups_paid * p.price_per_cup_cents, p.currency)}</div>
                    </button>
                ))}
            </div>

            <div className="flex flex-col gap-2">
                <div className="text-sm font-semibold text-muted">Betaling vastleggen</div>
                <div className="grid grid-cols-2 gap-2">
                    {(['pin', 'cash'] as Method[]).map((m) => (
                        <button
                            key={m}
                            onClick={() => setMethod(m)}
                            className={`rounded-xl border px-4 py-3 font-bold capitalize transition ${
                                method === m ? 'border-caramel bg-caramel/10' : 'border-black/10 bg-white'
                            }`}
                        >
                            {m === 'pin' ? 'Pin' : 'Contant'}
                        </button>
                    ))}
                </div>
            </div>

            {error && <Banner kind="error">{error}</Banner>}

            <button
                className="btn-primary w-full"
                disabled={busy || !productId || !customer.email_verified}
                onClick={confirm}
            >
                {busy ? 'Aanmaken…' : `${euro(priceCents, product?.currency)} ontvangen — kaart activeren`}
            </button>
        </div>
    )
}
