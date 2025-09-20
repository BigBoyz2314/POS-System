import React from 'react'

export interface CartItem {
  productId: number
  slug: string
  name: string
  price: number
  imageUrl?: string | null
  quantity: number
}

interface CartContextValue {
  items: CartItem[]
  addItem: (item: Omit<CartItem, 'quantity'>, quantity?: number) => void
  removeItem: (productId: number) => void
  updateQty: (productId: number, quantity: number) => void
  clear: () => void
  subtotal: number
  count: number
}

const CartContext = React.createContext<CartContextValue | undefined>(undefined)

const STORAGE_KEY = 'shop_cart_v1'

export function CartProvider({ children }: { children: React.ReactNode }): JSX.Element {
  const [items, setItems] = React.useState<CartItem[]>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      return raw ? JSON.parse(raw) : []
    } catch {
      return []
    }
  })

  React.useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
  }, [items])

  const addItem = React.useCallback((item: Omit<CartItem, 'quantity'>, quantity: number = 1) => {
    setItems(prev => {
      const idx = prev.findIndex(i => i.productId === item.productId)
      if (idx >= 0) {
        const next = [...prev]
        next[idx] = { ...next[idx], quantity: next[idx].quantity + quantity }
        return next
      }
      return [...prev, { ...item, quantity }]
    })
  }, [])

  const removeItem = React.useCallback((productId: number) => {
    setItems(prev => prev.filter(i => i.productId !== productId))
  }, [])

  const updateQty = React.useCallback((productId: number, quantity: number) => {
    setItems(prev => prev.map(i => i.productId === productId ? { ...i, quantity: Math.max(1, quantity) } : i))
  }, [])

  const clear = React.useCallback(() => setItems([]), [])

  const subtotal = React.useMemo(() => items.reduce((s, i) => s + i.price * i.quantity, 0), [items])
  const count = React.useMemo(() => items.reduce((s, i) => s + i.quantity, 0), [items])

  const value: CartContextValue = { items, addItem, removeItem, updateQty, clear, subtotal, count }
  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart(): CartContextValue {
  const ctx = React.useContext(CartContext)
  if (!ctx) throw new Error('useCart must be used within CartProvider')
  return ctx
}


