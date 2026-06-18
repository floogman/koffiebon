import React from 'react'
import ReactDOM from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './index.css'

import CustomerApp from './customer/App'
import DeeplinkPage from './customer/pages/DeeplinkPage'
import BalieApp from './balie/App'
import DashboardPage from './balie/pages/DashboardPage'

const queryClient = new QueryClient({
    defaultOptions: {
        queries: { retry: false, refetchOnWindowFocus: true },
    },
})

const router = createBrowserRouter([
    { path: '/', element: <CustomerApp /> },
    { path: '/s/:nonce', element: <DeeplinkPage /> },
    { path: '/balie/*', element: <BalieApp /> },
    { path: '/dashboard', element: <DashboardPage /> },
])

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>
    </React.StrictMode>,
)
