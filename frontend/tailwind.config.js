/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  safelist: [
    'dark:bg-gray-700',
    'dark:bg-gray-800',
    'dark:bg-gray-900',
    'dark:text-gray-100',
    'dark:text-gray-300',
    'dark:border-gray-600',
    'dark:border-gray-700',
    'dark:hover:bg-gray-700',
    'dark:hover:bg-gray-800',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
