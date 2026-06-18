import { makeApi } from './api'
import { customerToken, staffToken } from './auth'
import type { Card, Customer, QrToken, ScanResult, Staff, CardProduct, Drink, DashboardData } from './types'

const customer = makeApi(customerToken.get)
const staff = makeApi(staffToken.get)
const anon = makeApi(null)

export const customerApi = {
    loginRequest: (email: string, channelHash: string) =>
        anon.post<{ message: string }>('/auth/login-request', { email, channel_hash: channelHash }),
    claim: (secret: string) =>
        anon.post<{ device_token: string; customer: Customer }>('/auth/claim', { secret }),
    me: () => customer.get<Customer>('/pwa/me'),
    drinks: () => customer.get<{ data: Drink[] }>('/pwa/drinks'),
    card: (id: number) => customer.get<Card>(`/pwa/cards/${id}`),
    issueToken: (purpose: 'identify' | 'redeem', cardId?: number, preferred?: { type: string; size: string }) =>
        customer.post<QrToken>('/pwa/tokens', {
            purpose,
            card_id: cardId,
            preferred_coffee_type: preferred?.type,
            preferred_cup_size: preferred?.size,
        }),
}

export const staffApi = {
    login: (email: string, password: string) =>
        anon.post<{ staff_token: string; staff: Staff }>('/staff/login', { email, password }),
    logout: () => staff.post<{ message: string }>('/staff/logout'),
    products: () => staff.get<{ data: CardProduct[] }>('/staff/products'),
    drinks: () => staff.get<{ data: Drink[] }>('/staff/drinks'),
    scan: (nonce: string) => staff.post<ScanResult>('/staff/scan', { nonce }),
    dashboard: (params: { location_id?: number; from?: string; to?: string } = {}) => {
        const qs = new URLSearchParams()
        if (params.location_id) qs.set('location_id', String(params.location_id))
        if (params.from) qs.set('from', params.from)
        if (params.to) qs.set('to', params.to)
        const suffix = qs.toString() ? `?${qs}` : ''
        return staff.get<DashboardData>(`/staff/dashboard${suffix}`)
    },
    createCard: (
        customerId: number,
        productId: number,
        method: 'pin' | 'cash',
        preferred: { type: string; size: string },
    ) =>
        staff.post<{ card: Card }>('/staff/cards', {
            customer_id: customerId,
            card_product_id: productId,
            payment: { method },
            preferred_coffee_type: preferred.type,
            preferred_cup_size: preferred.size,
        }),
    activateCard: (cardId: number, method: 'pin' | 'cash') =>
        staff.post<{ card: Card }>(`/staff/cards/${cardId}/activate`, { payment: { method } }),
}

export function euro(cents: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency }).format(cents / 100)
}
