import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom'
import React from 'react'
import axios from 'axios'
import { useCart } from '../context/CartContext'

function useIsActive(path: string) {
  const { pathname } = useLocation()
  return pathname === path
}

export default function RootLayout(): JSX.Element {
  const isHome = useIsActive('/')
  const isCart = useIsActive('/cart')
  const navigate = useNavigate()
  const [biz, setBiz] = React.useState<{name?: string, logo?: string}>({})
  let count = 0
  try {
    // lazy read to avoid provider dependency here
    const raw = localStorage.getItem('shop_cart_v1')
    const parsed = raw ? JSON.parse(raw) : []
    count = parsed.reduce((s: number, i: any) => s + (i.quantity||0), 0)
  } catch {}

  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await axios.get('/api/settings.php')
        if (!mounted) return
        setBiz({ name: res.data?.settings?.business_name, logo: res.data?.settings?.logo_url })
      } catch {}
    })()
    return () => { mounted = false }
  }, [])

  const [query, setQuery] = React.useState('')

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault()
    const q = query.trim()
    navigate(q ? `/?q=${encodeURIComponent(q)}` : '/')
  }

  return (
    <div className="min-h-screen bg-white text-gray-900">
      <header className="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-200">
        <div className="max-w-6xl mx-auto px-4 py-3">
          <div className="flex items-center justify-between">
            <Link to="/" className="flex items-center space-x-2">
              <img src={biz.logo || '/logo-light.png'} alt="Logo" className="h-8 w-8" />
              <span className="font-semibold">{biz.name || 'Shop'}</span>
            </Link>
            <nav className="flex items-center space-x-2">
              <Link
                to="/"
                className={`px-3 py-2 rounded-md text-sm ${isHome ? 'text-blue-700 bg-blue-100/80' : 'text-gray-700 hover:bg-gray-100'}`}
              >Home</Link>
              <Link
                to="/cart"
                className={`px-3 py-2 rounded-md text-sm ${isCart ? 'text-blue-700 bg-blue-100/80' : 'text-gray-700 hover:bg-gray-100'}`}
              >Cart{count ? ` (${count})` : ''}</Link>
              <a href="/admin" className="px-3 py-2 rounded-md text-sm text-gray-700 hover:bg-gray-100">POS</a>
            </nav>
          </div>
          <form onSubmit={onSearch} className="mt-3">
            <input
              value={query}
              onChange={e=>setQuery(e.target.value)}
              className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Search products..."
            />
          </form>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-4 py-6">
        <Outlet />
      </main>

      <footer className="border-t border-gray-200 py-6 text-center text-sm text-gray-500">© Your Business</footer>
    </div>
  )
}


