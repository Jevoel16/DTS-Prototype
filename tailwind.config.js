/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    "./src/Views/**/*.php",
    "./public/js/**/*.js"
  ],
  theme: {
    extend: {
        fontFamily: {
            sans: ['"DM Sans"', 'sans-serif'],
        },
        colors: {
            primary: '#004281',
            secondary: '#f8f9fa',
            darkBg: '#111827',
            'accent-1': {
                DEFAULT: 'rgb(var(--color-accent-1) / <alpha-value>)',
                hover: 'rgb(var(--color-accent-1-hover) / <alpha-value>)',
                active: 'rgb(var(--color-accent-1-active) / <alpha-value>)',
                light: 'rgb(var(--color-accent-1-light) / <alpha-value>)',
                border: 'rgb(var(--color-accent-1-border) / <alpha-value>)',
                'border-active': 'rgb(var(--color-accent-1-border-active) / <alpha-value>)',
            },
            'accent-2': {
                DEFAULT: 'rgb(var(--color-accent-2) / <alpha-value>)',
                hover: 'rgb(var(--color-accent-2-hover) / <alpha-value>)',
                active: 'rgb(var(--color-accent-2-active) / <alpha-value>)',
                light: 'rgb(var(--color-accent-2-light) / <alpha-value>)',
            },
            'success': {
                DEFAULT: 'rgb(var(--color-success) / <alpha-value>)',
                hover: 'rgb(var(--color-success-hover) / <alpha-value>)',
                active: 'rgb(var(--color-success-active) / <alpha-value>)',
                light: 'rgb(var(--color-success-light) / <alpha-value>)',
            }
        }
    },
  },
  plugins: [],
}
