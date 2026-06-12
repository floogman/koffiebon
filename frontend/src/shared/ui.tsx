import type { ReactNode } from 'react'

export function Logo({ subtitle }: { subtitle?: string }) {
    return (
        <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-espresso text-xl">
                ☕
            </div>
            <div>
                <div className="text-lg font-extrabold leading-none">Koffiebon</div>
                {subtitle && <div className="text-xs text-muted">{subtitle}</div>}
            </div>
        </div>
    )
}

export function Spinner() {
    return (
        <div
            className="h-6 w-6 animate-spin rounded-full border-2 border-black/10 border-t-caramel"
            aria-label="Laden"
        />
    )
}

/** Voortgangsbalk van resterende koppen. */
export function CupProgress({ remaining, total }: { remaining: number; total: number }) {
    const pct = total > 0 ? Math.round((remaining / total) * 100) : 0
    return (
        <div className="h-3 w-full overflow-hidden rounded-full bg-black/10">
            <div
                className="h-full rounded-full bg-caramel transition-all"
                style={{ width: `${pct}%` }}
            />
        </div>
    )
}

export function Screen({ children }: { children: ReactNode }) {
    return <div className="mx-auto flex min-h-full w-full max-w-md flex-col px-5 py-6">{children}</div>
}

export function Banner({ kind, children }: { kind: 'info' | 'error' | 'ok'; children: ReactNode }) {
    const styles = {
        info: 'bg-caramel/10 text-bean',
        error: 'bg-red-100 text-red-800',
        ok: 'bg-green-100 text-green-800',
    }[kind]
    return <div className={`rounded-xl px-4 py-3 text-sm ${styles}`}>{children}</div>
}
