/**
 * Haalt de nonce uit gescande tekst. De QR bevat een deeplink ({app}/s/{nonce}),
 * maar een hardware-scanner kan ook een kale nonce sturen — beide werken.
 */
export function extractNonce(text: string): string {
    const trimmed = text.trim()
    const idx = trimmed.lastIndexOf('/s/')
    if (idx >= 0) return trimmed.slice(idx + 3).split(/[?#]/)[0]
    return trimmed
}
