/** @type {import('tailwindcss').Config} */
export default {
    content: ['./index.html', './src/**/*.{ts,tsx}'],
    theme: {
        extend: {
            colors: {
                espresso: '#1e1410',
                cream: '#f5ece1',
                caramel: '#c5772a',
                'caramel-dark': '#a8631f',
                bean: '#3a2a20',
                muted: '#8a7565',
            },
            fontFamily: {
                sans: ['-apple-system', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
            },
        },
    },
    plugins: [],
}
