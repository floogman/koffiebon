import { useEffect, useRef } from 'react'

/**
 * Ondersteuning voor een hardware-scanner (keyboard-wedge): die "typt" de code en
 * sluit af met Enter. We luisteren globaal mee, bufferen de tekens en submitten op
 * Enter. Snel getypte burst (scanner) vs. handmatig typen onderscheiden we op tijd.
 */
export default function HardwareScanInput({
    onScan,
    enabled,
}: {
    onScan: (text: string) => void
    enabled: boolean
}) {
    const buffer = useRef('')
    const lastKey = useRef(0)

    useEffect(() => {
        if (!enabled) return

        const onKeyDown = (e: KeyboardEvent) => {
            // Niet kapen als iemand in een invoerveld typt.
            const target = e.target as HTMLElement
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) return

            const now = Date.now()
            if (now - lastKey.current > 120) buffer.current = '' // nieuwe burst
            lastKey.current = now

            if (e.key === 'Enter') {
                const code = buffer.current.trim()
                buffer.current = ''
                if (code.length >= 8) onScan(code)
                return
            }

            if (e.key.length === 1) buffer.current += e.key
        }

        window.addEventListener('keydown', onKeyDown)
        return () => window.removeEventListener('keydown', onKeyDown)
    }, [onScan, enabled])

    return null
}
