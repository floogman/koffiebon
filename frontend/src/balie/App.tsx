import { useState } from 'react'
import type { Staff } from '@shared/types'
import { staffToken, staffUser } from '@shared/auth'
import LoginPage from './pages/LoginPage'
import ScanPage from './pages/ScanPage'

export default function BalieApp() {
    const [token, setToken] = useState<string | null>(staffToken.get())
    // Uit opslag, zodat de rol (admin → dashboardknop) een remount/refresh overleeft.
    const [staff, setStaff] = useState<Staff | null>(staffUser.get())

    const signOut = () => {
        staffToken.clear()
        staffUser.clear()
        setToken(null)
        setStaff(null)
    }

    if (!token) {
        return (
            <LoginPage
                onLoggedIn={(t, s) => {
                    staffToken.set(t)
                    staffUser.set(s)
                    setStaff(s)
                    setToken(t)
                }}
            />
        )
    }

    return <ScanPage staff={staff} onSignOut={signOut} />
}
