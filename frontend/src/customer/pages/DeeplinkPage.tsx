import { Logo, Screen } from '@shared/ui'

/**
 * Doelpagina van de QR-deeplink (/s/{nonce}). Wordt geopend als iemand de QR met
 * een gewone camera scant. De balie-app leest de nonce uit deze URL; hier tonen we
 * alleen een vriendelijke instructie.
 */
export default function DeeplinkPage() {
    return (
        <Screen>
            <div className="mb-8 mt-4">
                <Logo />
            </div>
            <div className="card flex flex-col items-center gap-3 p-8 text-center">
                <div className="text-5xl">☕</div>
                <h1 className="text-xl font-bold">Toon dit aan de balie</h1>
                <p className="text-sm text-muted">
                    Laat de medewerker deze code scannen. Open de Koffiebon-app om je eigen kaarten en
                    saldo te zien.
                </p>
                <a className="btn-primary mt-2 w-full" href="/">
                    Open mijn Koffiebon
                </a>
            </div>
        </Screen>
    )
}
