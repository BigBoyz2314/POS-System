import React from 'react'
import { Link } from 'react-router-dom'
import { api, PublicProduct } from '../lib/api'

export default function FeaturedCarousel(): JSX.Element {
  const [items, setItems] = React.useState<PublicProduct[]>([])
  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await api.get('/products_public.php')
        if (!mounted) return
        const featured = (res.data.products || []).filter((p: PublicProduct) => p.featured)
        setItems(featured.slice(0, 12))
      } catch {}
    })()
    return () => { mounted = false }
  }, [])

  if (!items.length) return <></>

  return (
    <div id="featured" className="mt-8">
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold">Featured</h2>
        <Link to="/" className="text-sm text-blue-600">View all</Link>
      </div>
      <div className="overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div className="flex gap-4 min-w-max">
          {items.map(p => (
            <Link key={p.id} to={`/product/${p.slug}`} className="w-44 flex-shrink-0 border rounded-lg p-3 hover:shadow bg-white">
              <div className="aspect-square bg-white rounded mb-2 overflow-hidden border">
                {p.image_url ? <img src={p.image_url} alt={p.name} className="w-full h-full object-cover" /> : null}
              </div>
              <div className="text-xs font-medium line-clamp-2">{p.name}</div>
              <div className="text-sm text-gray-600">Rs. {p.display_price}</div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  )
}


