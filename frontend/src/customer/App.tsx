import { useState } from 'react'
import { customerToken } from '@shared/auth'
import { disconnectEcho } from '@shared/echo'
import AuthPage from './pages/AuthPage'
import HomePage from './pages/HomePage'

export default function CustomerApp() {
    const [token, setToken] = useState<string | null>(customerToken.get())

    const signOut = () => {
        disconnectEcho()
        customerToken.clear()
        setToken(null)
    }

    if (!token) {
        return <AuthPage onAuthenticated={setToken} />
    }

    return <HomePage onSignOut={signOut} />
}
