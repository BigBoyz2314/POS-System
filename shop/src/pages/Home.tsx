import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import { api, PublicProduct } from '../lib/api'
import Hero from '../components/Hero'
import CategoryPills from '../components/CategoryPills'
import FeaturedCarousel from '../components/FeaturedCarousel'

export default function Home(): JSX.Element {
  const [products, setProducts] = React.useState<PublicProduct[]>([])
  const [loading, setLoading] = React.useState(true)
  const [error, setError] = React.useState<string | null>(null)
  const { search } = useLocation()
  const q = React.useMemo(() => new URLSearchParams(search).get('q') || '', [search])

  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await api.get('/products_public.php', { params: q ? { q } : undefined })
        if (!mounted) return
        setProducts(res.data.products || [])
      } catch (e: any) {
        setError('Failed to load products')
      } finally {
        setLoading(false)
      }
    })()
    return () => { mounted = false }
  }, [q])

  return (
    <div>
      {!q && <Hero />}
      <CategoryPills />
      {!q && <FeaturedCarousel />}
      {loading && <div className="grid grid-cols-2 md:grid-cols-4 gap-4">{Array.from({length:8}).map((_,i)=>(<div key={i} className="border rounded-lg p-3"><div className="aspect-square bg-gray-100 rounded mb-2" /><div className="h-4 bg-gray-100 rounded w-3/4 mb-2" /><div className="h-4 bg-gray-100 rounded w-1/3" /></div>))}</div>}
      {error && <div className="text-red-600 text-sm">{error}</div>}
      {!loading && !error && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {products.map(p => (
            <Link key={p.id} to={`/product/${p.slug}`} className="border rounded-lg p-3 hover:shadow transition-shadow">
              <div className="aspect-square bg-white rounded mb-2 overflow-hidden border">
                {p.image_url ? (
                  <img src={p.image_url} alt={p.name} className="w-full h-full object-cover" />
                ) : null}
              </div>
              <div className="text-sm font-medium">{p.name}</div>
              <div className="text-sm text-gray-600">Rs. {p.display_price}</div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}


