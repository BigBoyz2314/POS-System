import React from 'react'
import { Link } from 'react-router-dom'
import { useCart } from '../context/CartContext'

export default function Cart(): JSX.Element {
  const { items, updateQty, removeItem, subtotal } = useCart()

  if (!items.length) {
    return (
      <div className="text-center py-20">
        <p className="text-gray-600 mb-4">Your cart is empty.</p>
        <Link to="/" className="text-blue-600">Continue shopping</Link>
      </div>
    )
  }

  return (
    <div className="grid md:grid-cols-3 gap-6">
      <div className="md:col-span-2 space-y-4">
        {items.map(item => (
          <div key={item.productId} className="flex items-center border rounded p-3 gap-3">
            <div className="w-20 h-20 bg-gray-100 rounded overflow-hidden">
              {item.imageUrl ? <img src={item.imageUrl} alt={item.name} className="w-full h-full object-cover" /> : null}
            </div>
            <div className="flex-1">
              <div className="font-medium text-sm">{item.name}</div>
              <div className="text-sm text-gray-600">Rs. {item.price}</div>
            </div>
            <div className="flex items-center gap-2">
              <input type="number" min={1} value={item.quantity} onChange={e => updateQty(item.productId, parseInt(e.target.value||'1', 10))} className="w-16 border rounded px-2 py-1" />
              <button onClick={() => removeItem(item.productId)} className="text-red-600 text-sm">Remove</button>
            </div>
          </div>
        ))}
      </div>
      <div className="border rounded p-4 h-fit">
        <div className="flex justify-between text-sm mb-2"><span>Subtotal</span><span>Rs. {subtotal.toFixed(2)}</span></div>
        <Link to="/checkout" className="block w-full text-center bg-blue-600 text-white rounded px-4 py-2 mt-3">Checkout</Link>
      </div>
    </div>
  )
}


