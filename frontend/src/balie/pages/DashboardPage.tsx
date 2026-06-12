import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, Navigate } from 'react-router-dom'
import { euro, staffApi } from '@shared/clients'
import { staffToken } from '@shared/auth'
import { isApiError } from '@shared/api'
import type { DashboardData } from '@shared/types'
import { Banner, Logo, Spinner } from '@shared/ui'

/** Admin-dashboard met echte cijfers uit het grootboek, filterbaar op vestiging + periode. */
export default function DashboardPage() {
    const [locationId, setLocationId] = useState<number | undefined>(undefined)
    const [days, setDays] = useState(30)
    const hasToken = !!staffToken.get()

    const from = new Date(Date.now() - (days - 1) * 86400000).toISOString().slice(0, 10)
    const to = new Date().toISOString().slice(0, 10)

    const { data, isLoading, error } = useQuery({
        queryKey: ['dashboard', locationId, days],
        queryFn: () => staffApi.dashboard({ location_id: locationId, from, to }),
        enabled: hasToken,
    })

    if (!hasToken) return <Navigate to="/balie" replace />

    return (
        <div className="mx-auto w-full max-w-3xl px-5 py-6">
            <header className="mb-6 flex items-center justify-between">
                <Logo subtitle="Dashboard" />
                <Link className="btn-ghost px-3 py-2 text-sm" to="/balie">
                    ← Balie
                </Link>
            </header>

            {error ? (
                <Banner kind="error">
                    {isApiError(error) && error.status === 403
                        ? 'Alleen beheerders kunnen het dashboard bekijken.'
                        : 'Kon het dashboard niet laden.'}
                </Banner>
            ) : isLoading || !data ? (
                <div className="flex justify-center py-16">
                    <Spinner />
                </div>
            ) : (
                <Dashboard
                    data={data}
                    locationId={locationId}
                    onLocation={setLocationId}
                    days={days}
                    onDays={setDays}
                />
            )}
        </div>
    )
}

function Dashboard({
    data,
    locationId,
    onLocation,
    days,
    onDays,
}: {
    data: DashboardData
    locationId?: number
    onLocation: (id?: number) => void
    days: number
    onDays: (d: number) => void
}) {
    const s = data.summary
    const maxDrinkType = Math.max(1, ...data.by_drink.by_type.map((t) => t.total))
    const maxDay = Math.max(1, ...data.activity.map((a) => a.count))

    return (
        <div className="flex flex-col gap-5">
            {/* Filters */}
            <div className="flex flex-wrap items-center gap-2">
                <Chip active={!locationId} onClick={() => onLocation(undefined)}>
                    Alle vestigingen
                </Chip>
                {data.locations.map((l) => (
                    <Chip key={l.id} active={locationId === l.id} onClick={() => onLocation(l.id)}>
                        {l.name}
                    </Chip>
                ))}
                <span className="mx-1 text-black/15">|</span>
                {[7, 30, 90].map((d) => (
                    <Chip key={d} active={days === d} onClick={() => onDays(d)}>
                        {d} dagen
                    </Chip>
                ))}
            </div>

            {/* KPI's */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Stat label="Omzet (kaartverkoop)" value={euro(s.revenue_cents)} />
                <Stat label="Geschonken koppen" value={String(s.cups_redeemed)} />
                <Stat label="Openstaande koppen" value={String(s.cups_outstanding)} hint="verplichting" />
                <Stat label="Actieve kaarten" value={String(s.active_cards)} hint={`${s.cards_sold} verkocht`} />
                <Stat label="Kostprijs geschonken" value={euro(s.drink_cost_cents)} />
                <Stat
                    label="Terugkerende klanten"
                    value={`${data.customers.returning}/${data.customers.total}`}
                    hint={`gem. ${data.customers.avg_cups_per_customer} koppen`}
                />
            </div>

            {/* Per vestiging */}
            <Panel title="Per vestiging">
                <table className="w-full text-sm">
                    <thead className="text-left text-muted">
                        <tr>
                            <th className="py-1">Vestiging</th>
                            <th className="py-1 text-right">Kaarten</th>
                            <th className="py-1 text-right">Geschonken</th>
                            <th className="py-1 text-right">Open</th>
                            <th className="py-1 text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.by_location.map((l) => (
                            <tr key={l.id} className="border-t border-black/5">
                                <td className="py-2 font-semibold">{l.name}</td>
                                <td className="py-2 text-right">{l.cards}</td>
                                <td className="py-2 text-right">{l.cups_redeemed}</td>
                                <td className="py-2 text-right">{l.cups_outstanding}</td>
                                <td className="py-2 text-right">{euro(l.revenue_cents)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Panel>

            {/* Drukte */}
            <Panel title={`Drukte — geschonken koppen per dag (${days}d)`}>
                <div className="flex h-32 items-end gap-[3px]">
                    {data.activity.map((a) => (
                        <div
                            key={a.date}
                            className="flex-1 rounded-t bg-caramel/80"
                            style={{ height: `${Math.max(3, (a.count / maxDay) * 100)}%` }}
                            title={`${a.date}: ${a.count}`}
                        />
                    ))}
                </div>
            </Panel>

            {/* Populairste drankjes */}
            <div className="grid gap-4 sm:grid-cols-2">
                <Panel title="Populairste koffie">
                    <div className="flex flex-col gap-2">
                        {data.by_drink.by_type.map((t) => (
                            <div key={t.type} className="flex items-center gap-2">
                                <div className="w-24 shrink-0 text-sm">{t.label}</div>
                                <div className="h-4 flex-1 overflow-hidden rounded bg-black/5">
                                    <div
                                        className="h-full rounded bg-espresso"
                                        style={{ width: `${(t.total / maxDrinkType) * 100}%` }}
                                    />
                                </div>
                                <div className="w-8 text-right text-sm font-semibold">{t.total}</div>
                            </div>
                        ))}
                    </div>
                </Panel>
                <Panel title="Per maat">
                    <div className="flex h-full items-center justify-around">
                        {data.by_drink.by_size.map((z) => (
                            <div key={z.size} className="text-center">
                                <div className="text-3xl font-extrabold text-caramel">{z.count}</div>
                                <div className="text-sm text-muted">{z.label}</div>
                            </div>
                        ))}
                    </div>
                </Panel>
            </div>
        </div>
    )
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="card p-4">
            <div className="text-xs text-muted">{label}</div>
            <div className="mt-1 text-2xl font-extrabold">{value}</div>
            {hint && <div className="text-xs text-muted">{hint}</div>}
        </div>
    )
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="card p-4">
            <div className="mb-3 text-sm font-bold text-bean">{title}</div>
            {children}
        </div>
    )
}

function Chip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            onClick={onClick}
            className={`rounded-full px-3 py-1.5 text-sm font-semibold transition ${
                active ? 'bg-espresso text-cream' : 'bg-black/5 text-bean'
            }`}
        >
            {children}
        </button>
    )
}
