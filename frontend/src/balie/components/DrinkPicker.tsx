import { useMemo } from 'react'
import type { Drink } from '@shared/types'

/**
 * Kiest het geschonken drankje (koffiesoort × maat). De selectie wordt met de
 * volgende scan meegestuurd voor de analytics; één scan blijft één kop.
 */
export default function DrinkPicker({
    drinks,
    type,
    size,
    onType,
    onSize,
}: {
    drinks: Drink[]
    type: string
    size: string
    onType: (t: string) => void
    onSize: (s: string) => void
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
            <div className="text-sm font-semibold text-muted">Wat schenk je?</div>
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
