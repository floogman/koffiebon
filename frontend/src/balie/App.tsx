import { useState } from 'react'
import type { Staff } from '@shared/types'
import { staffToken } from '@shared/auth'
import LoginPage from './pages/LoginPage'
import ScanPage from './pages/ScanPage'

export default function BalieApp() {
    const [token, setToken] = useState<string | null>(staffToken.get())
    const [staff, setStaff] = useState<Staff | null>(null)

    const signOut = () => {
        staffToken.clear()
        setToken(null)
        setStaff(null)
    }

    if (!token) {
        return (
            <LoginPage
                onLoggedIn={(t, s) => {
                    staffToken.set(t)
                    setStaff(s)
                    setToken(t)
                }}
            />
        )
    }

    return <ScanPage staff={staff} onSignOut={signOut} />
}
