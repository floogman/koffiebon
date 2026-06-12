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

export interface Drink {
    id: number
    type: string
    type_label: string
    size: string
    size_label: string
    cost_cents: number
}

export type ScanResult =
    | { type: 'identify'; customer: Customer; products: CardProduct[] }
    | { type: 'redeem'; result: 'redeemed'; card: Card; drink: Drink | null; customer: { id: number; email: string } }
    | { type: 'redeem'; result: 'needs_activation'; message: string; card: Card }

export interface DashboardData {
    range: { from: string; to: string }
    locations: { id: number; name: string }[]
    summary: {
        cards_sold: number
        active_cards: number
        cups_outstanding: number
        cups_redeemed: number
        revenue_cents: number
        drink_cost_cents: number
    }
    by_location: {
        id: number
        name: string
        cards: number
        cups_redeemed: number
        cups_outstanding: number
        revenue_cents: number
    }[]
    by_drink: {
        by_type: { type: string; label: string; sizes: Record<string, number>; total: number }[]
        by_size: { size: string; label: string; count: number }[]
    }
    activity: { date: string; count: number }[]
    customers: { total: number; returning: number; one_time: number; avg_cups_per_customer: number }
}

export interface ApiError {
    code?: string
    message: string
    errors?: Record<string, string[]>
    status: number
}
