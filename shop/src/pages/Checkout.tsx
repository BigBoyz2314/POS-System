import React from 'react'
import { useNavigate } from 'react-router-dom'
import { useCart } from '../context/CartContext'
import { api } from '../lib/api'

export default function Checkout(): JSX.Element {
  const navigate = useNavigate()
  const { items, clear, subtotal } = useCart()
  const [name, setName] = React.useState('')
  const [phone, setPhone] = React.useState('')
  const [address, setAddress] = React.useState('')
  const [loading, setLoading] = React.useState(false)
  const [error, setError] = React.useState<string | null>(null)

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!items.length) { setError('Cart is empty'); return }
    setLoading(true); setError(null)
    try {
      const payload = {
        customer_name: name,
        customer_phone: phone,
        customer_address: address,
        items: items.map(i => ({ productId: i.productId, quantity: i.quantity })),
      }
      const res = await api.post('/order_create.php', payload)
      clear()
      navigate(`/`)
      alert(`Order placed: ${res.data.order_number}\nTotal: Rs. ${res.data.total}`)
    } catch (err: any) {
      setError(err?.response?.data?.error || 'Failed to place order')
    } finally {
      setLoading(false)
    }
  }

  return (
    <form className="max-w-lg mx-auto space-y-4" onSubmit={onSubmit}>
      <h1 className="text-2xl font-semibold mb-2">Checkout</h1>
      {error && <div className="text-red-600 text-sm">{error}</div>}
      <input className="w-full border rounded px-3 py-2" placeholder="Name" value={name} onChange={e=>setName(e.target.value)} />
      <input className="w-full border rounded px-3 py-2" placeholder="Phone" value={phone} onChange={e=>setPhone(e.target.value)} />
      <textarea className="w-full border rounded px-3 py-2" placeholder="Address" value={address} onChange={e=>setAddress(e.target.value)} />
      <button disabled={loading} className="w-full bg-blue-600 disabled:opacity-60 text-white rounded px-4 py-2">{loading ? 'Placing...' : 'Place Order (COD)'}</button>
      <div className="text-sm text-gray-600">Subtotal: Rs. {subtotal.toFixed(2)}</div>
    </form>
  )
}


