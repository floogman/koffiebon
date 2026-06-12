export type CardStatus = 'pending' | 'active' | 'depleted' | 'expired' | 'void'

export interface CardProduct {
    id: number
    name: string
    cups_total: number
    cups_paid: number
    price_per_cup_cents: number
    currency: string
    validity_days?: number
    card_price_cents?: number
    gift_cups?: number
    discount_rate?: number
}

export interface Card {
    id: number
    status: CardStatus
    cups_total: number
    cups_remaining: number
    price_paid_cents: number | null
    activated_at: string | null
    expires_at: string | null
    product?: Pick<CardProduct, 'id' | 'name' | 'cups_total' | 'cups_paid' | 'price_per_cup_cents' | 'currency'>
}

export interface Customer {
    id: number
    email: string
    name: string | null
    email_verified: boolean
    cards: Card[]
}

export interface QrToken {
    nonce: string
    purpose: 'identify' | 'redeem'
    expires_at: string
    url: string
}

export interface Staff {
    id: number
    name: string
    role: 'admin' | 'balie'
    merchant_id: number
    location_id: number | null
}

export type ScanResult =
    | { type: 'identify'; customer: Customer; products: CardProduct[] }
    | { type: 'redeem'; result: 'redeemed'; card: Card; customer: { id: number; email: string } }
    | { type: 'redeem'; result: 'needs_activation'; message: string; card: Card }

export interface ApiError {
    code?: string
    message: string
    errors?: Record<string, string[]>
    status: number
}
