import { useState } from 'react'
import { customerToken } from '@shared/auth'
import AuthPage from './pages/AuthPage'
import HomePage from './pages/HomePage'

export default function CustomerApp() {
    const [token, setToken] = useState<string | null>(customerToken.get())

    const signOut = () => {
        customerToken.clear()
        setToken(null)
    }

    if (!token) {
        return <AuthPage />
    }

    return <HomePage onSignOut={signOut} />
}
