import { useEffect, useRef, useState } from 'react'
import { BrowserQRCodeReader } from '@zxing/browser'
import type { IScannerControls } from '@zxing/browser'

/**
 * Camera-scanner via @zxing/browser. Roept onResult met de ruwe QR-tekst.
 * Een korte cooldown voorkomt dat dezelfde code meermaals achter elkaar afgaat.
 */
export default function CameraScanner({ onResult }: { onResult: (text: string) => void }) {
    const videoRef = useRef<HTMLVideoElement>(null)
    const controlsRef = useRef<IScannerControls | null>(null)
    const lastRef = useRef<{ text: string; at: number }>({ text: '', at: 0 })
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        const reader = new BrowserQRCodeReader()
        let cancelled = false

        reader
            .decodeFromVideoDevice(undefined, videoRef.current!, (result) => {
                if (cancelled || !result) return
                const text = result.getText()
                const now = Date.now()
                // Debounce identieke scans binnen 2.5s.
                if (text === lastRef.current.text && now - lastRef.current.at < 2500) return
                lastRef.current = { text, at: now }
                onResult(text)
            })
            .then((controls) => {
                if (cancelled) controls.stop()
                else controlsRef.current = controls
            })
            .catch(() => setError('Kan de camera niet openen. Geef toegang of gebruik een hardware-scanner.'))

        return () => {
            cancelled = true
            controlsRef.current?.stop()
        }
    }, [onResult])

    return (
        <div className="overflow-hidden rounded-2xl bg-black">
            {error ? (
                <div className="flex h-56 items-center justify-center px-6 text-center text-sm text-white/80">
                    {error}
                </div>
            ) : (
                <div className="relative">
                    <video ref={videoRef} className="h-56 w-full object-cover" muted playsInline />
                    <div className="pointer-events-none absolute inset-0 m-auto h-40 w-40 rounded-xl border-2 border-white/70" />
                </div>
            )}
        </div>
    )
}
