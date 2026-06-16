import { useMemo } from 'react'
import type { Drink } from './types'

/**
 * Kiest een koffiesoort × maat uit de drankenkaart. Gedeeld tussen de balie
 * (welk drankje schenk je) en de klant-PWA (welk vast drankje hoort bij de kaart).
 */
export default function DrinkPicker({
    drinks,
    type,
    size,
    onType,
    onSize,
    title = 'Wat schenk je?',
}: {
    drinks: Drink[]
    type: string
    size: string
    onType: (t: string) => void
    onSize: (s: string) => void
    title?: string
}) {
    const types = useMemo(() => {
        const seen = new Map<string, string>()
        drinks.forEach((d) => seen.set(d.type, d.type_label))
        return [...seen.entries()]
    }, [drinks])

    const sizes = useMemo(() => {
        const seen = new Map<string, string>()
        drinks.forEach((d) => seen.set(d.size, d.size_label))
        return [...seen.entries()]
    }, [drinks])

    return (
        <div className="card flex flex-col gap-3 p-4">
            <div className="text-sm font-semibold text-muted">{title}</div>
            <div className="flex flex-wrap gap-2">
                {types.map(([value, label]) => (
                    <button
                        key={value}
                        onClick={() => onType(value)}
                        className={`rounded-full px-3 py-1.5 text-sm font-semibold transition ${
                            type === value ? 'bg-espresso text-cream' : 'bg-black/5 text-bean'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <div className="flex gap-2">
                {sizes.map(([value, label]) => (
                    <button
                        key={value}
                        onClick={() => onSize(value)}
                        className={`flex-1 rounded-xl px-3 py-2 text-sm font-bold transition ${
                            size === value ? 'border border-caramel bg-caramel/10 text-bean' : 'border border-black/10 bg-white'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>
        </div>
    )
}
