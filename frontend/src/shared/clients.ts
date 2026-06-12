import { makeApi } from './api'
import { customerToken, staffToken } from './auth'
import type { Card, Customer, QrToken, ScanResult, Staff, CardProduct } from './types'

const customer = makeApi(customerToken.get)
const staff = makeApi(staffToken.get)
const anon = makeApi(null)

export const customerApi = {
    register: (email: string) => anon.post<{ message: string }>('/auth/register', { email }),
    magicLink: (email: string) => anon.post<{ message: string }>('/auth/magic-link', { email }),
    claim: (code: string) =>
        anon.post<{ device_token: string; customer: Customer }>('/auth/claim', { code }),
    me: () => customer.get<Customer>('/pwa/me'),
    card: (id: number) => customer.get<Card>(`/pwa/cards/${id}`),
    issueToken: (purpose: 'identify' | 'redeem', cardId?: number) =>
        customer.post<QrToken>('/pwa/tokens', { purpose, card_id: cardId }),
}

export const staffApi = {
    login: (email: string, password: string) =>
        anon.post<{ staff_token: string; staff: Staff }>('/staff/login', { email, password }),
    logout: () => staff.post<{ message: string }>('/staff/logout'),
    products: () => staff.get<{ data: CardProduct[] }>('/staff/products'),
    scan: (nonce: string) => staff.post<ScanResult>('/staff/scan', { nonce }),
    createCard: (customerId: number, productId: number, method: 'pin' | 'cash') =>
        staff.post<{ card: Card }>('/staff/cards', {
            customer_id: customerId,
            card_product_id: productId,
            payment: { method },
        }),
    activateCard: (cardId: number, method: 'pin' | 'cash') =>
        staff.post<{ card: Card }>(`/staff/cards/${cardId}/activate`, { payment: { method } }),
}

export function euro(cents: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency }).format(cents / 100)
}
