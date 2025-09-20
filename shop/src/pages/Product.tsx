import React from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useCart } from '../context/CartContext'

export default function Product(): JSX.Element {
  const { slug } = useParams()
  const [loading, setLoading] = React.useState(true)
  const [error, setError] = React.useState<string | null>(null)
  const [product, setProduct] = React.useState<any>(null)
  const { addItem } = useCart()

  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await api.get('/product_detail.php', { params: { slug } })
        if (!mounted) return
        setProduct(res.data.product)
      } catch (e: any) {
        setError('Failed to load product')
      } finally {
        setLoading(false)
      }
    })()
    return () => { mounted = false }
  }, [slug])

  return (
    <div className="grid md:grid-cols-2 gap-6">
      <div className="aspect-square bg-gray-100 rounded overflow-hidden">
        {product?.images?.[0] ? (
          <img src={product.images[0]} alt={product.name} className="w-full h-full object-cover" />
        ) : null}
      </div>
      <div>
        {loading ? (
          <>
            <div className="h-6 bg-gray-100 rounded w-2/3 mb-3" />
            <div className="h-5 bg-gray-100 rounded w-1/3 mb-4" />
            <div className="h-20 bg-gray-100 rounded w-full mb-6" />
            <div className="h-10 bg-gray-100 rounded w-40" />
          </>
        ) : error ? (
          <div className="text-red-600 text-sm">{error}</div>
        ) : (
          <>
            <h1 className="text-2xl font-semibold mb-2">{product.name}</h1>
            <div className="text-lg text-gray-700 mb-4">Rs. {product.display_price}</div>
            <p className="text-sm text-gray-600 mb-6">{product.description || ''}</p>
            <button
              className="px-4 py-2 rounded bg-blue-600 text-white"
              onClick={() => product && addItem({
                productId: product.id,
                slug: product.slug,
                name: product.name,
                price: product.display_price,
                imageUrl: product.images?.[0] || null,
              }, 1)}
            >Add to Cart</button>
          </>
        )}
      </div>
    </div>
  )
}


