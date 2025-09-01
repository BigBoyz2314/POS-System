import React, { useState, useEffect, useMemo, useRef } from 'react';
import { apiService } from '../services/api'
import { useHeader } from '../contexts/HeaderContext'

interface Product {
  id: number
  name: string
  sku?: string
  category_name?: string
  price: number // tax-inclusive price (mirrors PHP)
  stock: number
  tax_rate?: number // percent
}

interface CartItem {
  product: Product
  quantity: number
}

interface ParkedCart {
  id: string
  note: string
  createdAt: string
  cart: CartItem[]
}

interface SaleItem {
  sale_item_id: number
  product_id: number
  product_name: string
  quantity: number
  price: number
  tax_rate: number
  returned_qty: number
  remaining_qty: number
}

interface ApiSaleItem {
  id: number
  product_id?: number
  product_name: string
  quantity: number
  price: number
  tax_rate: number
}

interface Sale {
  id: number
  date: string
  total_amount: number
  items: SaleItem[]
}

interface ApiSale {
  id: number
  date: string
  total_amount: number
  items: ApiSaleItem[]
}

type PaymentMethod = 'cash' | 'card' | 'mixed'
type SearchLayout = 'grid' | 'list'

export default function Sales() {
  const [products, setProducts] = useState<Product[]>([])
  const [cart, setCart] = useState<CartItem[]>([])
  const [searchTerm, setSearchTerm] = useState('')
  const [loading, setLoading] = useState(true)
  const [discountAmount, setDiscountAmount] = useState(0)
  const [paymentOpen, setPaymentOpen] = useState(false)
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash')
  const [cashAmount, setCashAmount] = useState(0)
  const [cardAmount, setCardAmount] = useState(0)

  const { showHeader, toggleHeader } = useHeader()
  const [controlsOpen, setControlsOpen] = useState(false)
  const [parkNoteOpen, setParkNoteOpen] = useState(false)
  const [parkNote, setParkNote] = useState('')
  const [parkedOpen, setParkedOpen] = useState(false)
  const [parkedCarts, setParkedCarts] = useState<ParkedCart[]>([])
  const [returnsOpen, setReturnsOpen] = useState(false)
  const [searchLayout, setSearchLayout] = useState<SearchLayout>('grid')
  
  // Returns/Exchanges state
  const [salesLookupOpen, setSalesLookupOpen] = useState(false)
  const [salesList, setSalesList] = useState<Sale[]>([])
  const [selectedSale, setSelectedSale] = useState<Sale | null>(null)
  const [returnItems, setReturnItems] = useState<Array<{
    sale_item_id: number
    product_id: number
    product_name: string
    quantity: number
    return_qty: number
    reason: string
    refund_amount: number
  }>>([])
  const [refundMethod, setRefundMethod] = useState<'cash' | 'card' | 'mixed'>('cash')
  const [refundCash, setRefundCash] = useState(0)
  const [refundCard, setRefundCard] = useState(0)

  // Highlight newly added items
  const [newlyAddedItems, setNewlyAddedItems] = useState<Set<number>>(new Set())
  
  // Track if cart has been loaded from localStorage
  const cartLoadedRef = useRef(false)

  // Invoice state
  const [invoiceOpen, setInvoiceOpen] = useState(false)
  const [invoiceData, setInvoiceData] = useState<{
    id: number
    items: Array<{
      name: string
      quantity: number
      price: number
      tax_rate: number
      total: number
    }>
    total: number
    tax: number
    discount: number
    final: number
    payment_method: string
    cash_amount: number
    card_amount: number
    balance: number
  } | null>(null)

  // keyboard shortcuts: F2/Ctrl+F focus search, Ctrl+Enter payment, Alt+P payment, Alt+K park, Alt+R parked, Delete clear (confirm)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const key = e.key
      const lower = key.toLowerCase()
      // Focus search
      if ((e.ctrlKey || e.metaKey) && lower === 'f') {
        e.preventDefault()
        const input = (document.getElementById('searchInputReact') || document.getElementById('searchInput')) as HTMLInputElement | null
        input?.focus()
        return
      }
      if (key === 'F2') {
        e.preventDefault()
        const input = (document.getElementById('searchInputReact') || document.getElementById('searchInput')) as HTMLInputElement | null
        input?.focus()
        return
      }
      // Open payment
      if ((e.ctrlKey && key === 'Enter') || (e.altKey && lower === 'p')) {
        e.preventDefault()
        if (cart.length > 0) setPaymentOpen(true)
        return
      }
      // Open park note
      if (e.altKey && lower === 'k') {
        e.preventDefault()
        if (cart.length > 0) { setParkNote(''); setParkNoteOpen(true) }
        return
      }
      // Open parked list
      if (e.altKey && lower === 'r') {
        e.preventDefault()
        setParkedOpen(true)
        return
      }
      // Clear cart with confirmation
      if (key === 'Delete') {
        if (cart.length > 0 && confirm('Clear all items from the cart?')) setCart([])
        return
      }
      // Clear cart with Alt+C
      if (e.altKey && lower === 'c') {
        e.preventDefault()
        if (cart.length > 0 && confirm('Clear all items from the cart?')) setCart([])
        return
      }
      // Increase/decrease last item quantity
      if (key === '+' || key === '=') {
        e.preventDefault()
        if (cart.length > 0) {
          const lastItem = cart[cart.length - 1]
          updateQuantity(lastItem.product.id, lastItem.quantity + 1)
        }
        return
      }
      if (key === '-') {
        e.preventDefault()
        if (cart.length > 0) {
          const lastItem = cart[cart.length - 1]
          updateQuantity(lastItem.product.id, lastItem.quantity - 1)
        }
        return
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [cart.length])

  // Load any parked cart and parked list on mount
  useEffect(() => {
    if (cartLoadedRef.current) return // Already loaded
    
    try {
      const saved = localStorage.getItem('pos_cart')
      console.log('Loading cart from localStorage:', saved)
      if (saved) {
        const parsed: CartItem[] = JSON.parse(saved)
        console.log('Parsed cart:', parsed)
        if (Array.isArray(parsed) && parsed.length > 0) {
          setCart(parsed)
          console.log('Cart loaded successfully with', parsed.length, 'items')
        } else {
          console.log('Parsed cart is empty or invalid')
        }
      } else {
        console.log('No saved cart found in localStorage')
      }
      const savedParked = localStorage.getItem('parked_carts')
      if (savedParked) {
        const parsedParked: ParkedCart[] = JSON.parse(savedParked)
        if (Array.isArray(parsedParked)) setParkedCarts(parsedParked)
      }
      
      // Load search layout preference
      const savedLayout = localStorage.getItem('search_layout')
      if (savedLayout === 'list' || savedLayout === 'grid') {
        setSearchLayout(savedLayout as SearchLayout)
      }
      
      cartLoadedRef.current = true
    } catch (error) {
      console.error('Error loading cart from localStorage:', error)
      cartLoadedRef.current = true
    }
  }, [])

  // Persist cart on changes (like PHP storing on errors)
  useEffect(() => {
    try {
      console.log('Saving cart to localStorage:', cart)
      if (cart.length > 0) {
        localStorage.setItem('pos_cart', JSON.stringify(cart))
        console.log('Cart saved successfully')
      } else {
        localStorage.removeItem('pos_cart')
        console.log('Cart cleared from localStorage')
      }
    } catch (error) {
      console.error('Error saving cart to localStorage:', error)
    }
  }, [cart])

  // Persist search layout preference
  useEffect(() => {
    try {
      localStorage.setItem('search_layout', searchLayout)
    } catch (error) {
      console.error('Error saving search layout to localStorage:', error)
    }
  }, [searchLayout])

  useEffect(() => {
    loadProducts()
  }, [])

  const coalesceString = (...vals: unknown[]) => {
    for (const v of vals) {
      if (v !== undefined && v !== null) {
        const s = String(v)
        if (s.trim().length > 0) return s
      }
    }
    return ''
  }

  const loadProducts = async () => {
    try {
      const response = await apiService.getProducts()
      const data = response.data
      const arrayData: unknown = data
      const normalized: Product[] = Array.isArray(arrayData)
        ? arrayData.map((p: any) => ({
            id: Number(p.id),
            name: coalesceString(p.name),
            sku: coalesceString(p.sku, p.SKU, p.product_sku, p.code, p.product_code),
            category_name: coalesceString(p.category_name, p.category),
            price: Number(p.price ?? 0),
            stock: Number(p.stock ?? 0),
            tax_rate: p.tax_rate != null ? Number(p.tax_rate) : 0,
          }))
        : []
      setProducts(normalized)
    } catch (error) {
      console.error('Error loading products:', error)
      setProducts([])
    } finally {
      setLoading(false)
    }
  }

  const addToCart = (product: Product) => {
    setCart(prevCart => {
      const existingItem = prevCart.find(item => item.product.id === product.id)
      const currentQuantityInCart = existingItem ? existingItem.quantity : 0
      const newQuantity = currentQuantityInCart + 1
      
      // Check if adding this item would exceed available stock
      if (newQuantity > product.stock) {
        // Show low stock alert
        const remainingStock = product.stock - currentQuantityInCart
        if (remainingStock <= 0) {
          alert(`⚠️ Out of Stock!\n\n"${product.name}" is currently out of stock.\nAvailable: ${product.stock}\nIn cart: ${currentQuantityInCart}`)
        } else {
          alert(`⚠️ Low Stock Alert!\n\n"${product.name}" has limited stock remaining.\nAvailable: ${product.stock}\nIn cart: ${currentQuantityInCart}\nRemaining: ${remainingStock}`)
        }
        return prevCart // Don't add to cart
      }
      
      let addedItemIndex = -1
      
      if (existingItem) {
        // Existing item - increase quantity and highlight
        const newCart = prevCart.map(item =>
          item.product.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        )
        addedItemIndex = newCart.findIndex(item => item.product.id === product.id)
        // Clear all highlights and only highlight this item
        setNewlyAddedItems(new Set([product.id]))
        
        // Scroll to the added item after a brief delay
        setTimeout(() => {
          const cartRows = document.querySelectorAll('#cartItems tbody tr')
          if (addedItemIndex >= 0 && cartRows[addedItemIndex]) {
            cartRows[addedItemIndex].scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            })
          }
        }, 100)
        
        return newCart
      } else {
        // New item - add to cart and highlight
        const newCart = [...prevCart, { product, quantity: 1 }]
        addedItemIndex = newCart.length - 1
        // Clear all highlights and only highlight this item
        setNewlyAddedItems(new Set([product.id]))
        
        // Scroll to the added item after a brief delay
        setTimeout(() => {
          const cartRows = document.querySelectorAll('#cartItems tbody tr')
          if (addedItemIndex >= 0 && cartRows[addedItemIndex]) {
            cartRows[addedItemIndex].scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            })
          }
        }, 100)
        
        return newCart
      }
    })
  }

  // Clear highlight after 2 seconds
  useEffect(() => {
    if (newlyAddedItems.size > 0) {
      const timer = setTimeout(() => {
        setNewlyAddedItems(new Set())
      }, 2000)
      return () => clearTimeout(timer)
    }
  }, [newlyAddedItems])

  const removeFromCart = (productId: number) => {
    setCart(prevCart => prevCart.filter(item => item.product.id !== productId))
    // Clear highlight when item is removed
    setNewlyAddedItems(prev => {
      const newSet = new Set(prev)
      newSet.delete(productId)
      return newSet
    })
  }

  const updateQuantity = (productId: number, quantity: number) => {
    if (quantity <= 0) {
      removeFromCart(productId)
      return
    }
    
    setCart(prevCart => {
      const cartItem = prevCart.find(item => item.product.id === productId)
      if (!cartItem) return prevCart
      
      // Check if new quantity exceeds available stock
      if (quantity > cartItem.product.stock) {
        alert(`⚠️ Low Stock Alert!\n\n"${cartItem.product.name}" has limited stock.\nAvailable: ${cartItem.product.stock}\nRequested: ${quantity}\n\nQuantity has been set to maximum available stock.`)
        // Set to maximum available stock
        return prevCart.map(item =>
          item.product.id === productId
            ? { ...item, quantity: cartItem.product.stock }
            : item
        )
      }
      
      return prevCart.map(item =>
        item.product.id === productId
          ? { ...item, quantity }
          : item
      )
    })
    
    // Clear highlight when quantity is manually updated
    setNewlyAddedItems(prev => {
      const newSet = new Set(prev)
      newSet.delete(productId)
      return newSet
    })
  }

  const calculateTotals = () => {
    let subtotal = 0
    let tax = 0
    let total = 0
    for (const item of cart) {
      const rate = Number(item.product.tax_rate || 0)
      const lineTotal = item.product.price * item.quantity
      const lineSubtotal = rate > 0 ? (lineTotal / (1 + rate / 100)) : lineTotal
      const lineTax = lineTotal - lineSubtotal
      subtotal += lineSubtotal
      tax += lineTax
      total += lineTotal
    }
    const discountedTotal = Math.max(0, total - discountAmount)
    let finalSubtotal = subtotal
    let finalTax = tax
    if (discountAmount > 0 && total > 0) {
      const factor = (discountedTotal) / total
      finalSubtotal = subtotal * factor
      finalTax = tax * factor
    }
    return { subtotal: finalSubtotal, tax: finalTax, total: discountedTotal }
  }

  const { subtotal, tax, total } = calculateTotals()

  const saveParked = (list: ParkedCart[]) => {
    setParkedCarts(list)
    try { localStorage.setItem('parked_carts', JSON.stringify(list)) } catch {}
  }

  const openParkModal = () => { setParkNote(''); setParkNoteOpen(true) }

  const parkCart = () => {
    if (cart.length === 0) return
    const entry: ParkedCart = { id: String(Date.now()), note: parkNote.trim(), createdAt: new Date().toISOString(), cart }
    const next = [entry, ...parkedCarts]
    saveParked(next)
    setParkNoteOpen(false)
    setCart([])
  }

  const resumeFromEntry = (entry: ParkedCart) => {
    setCart(entry.cart)
    const next = parkedCarts.filter(p => p.id !== entry.id)
    saveParked(next)
    setParkedOpen(false)
  }

  const deleteParked = (entry: ParkedCart) => { saveParked(parkedCarts.filter(p => p.id !== entry.id)) }

  const clearCart = () => { 
    if (cart.length === 0 || confirm('Clear all items from the cart?')) {
      setCart([])
      localStorage.removeItem('pos_cart')
    }
  }

  // Returns/Exchanges functions
  const loadSalesList = async () => {
    try {
      const response = await apiService.getSales()
      setSalesList(response.data || [])
    } catch (error) {
      console.error('Error loading sales:', error)
      setSalesList([])
    }
  }

  const loadSaleForReturn = async (saleId: number) => {
    try {
      const response = await apiService.getSaleDetails(saleId)
      const apiSale: ApiSale = response.data.sale // Access the sale property from response
      
      // Convert API sale to internal Sale format
      const sale: Sale = {
        id: apiSale.id,
        date: apiSale.date,
        total_amount: apiSale.total_amount,
        items: apiSale.items.map(item => ({
          sale_item_id: item.id,
          product_id: item.product_id || 0, // Ensure product_id is available
          product_name: item.product_name,
          quantity: item.quantity,
          price: item.price,
          tax_rate: item.tax_rate,
          returned_qty: 0,
          remaining_qty: item.quantity
        }))
      }
      
      setSelectedSale(sale)
      
      // Initialize return items
      const items = sale.items.map((item: SaleItem) => ({
        sale_item_id: item.sale_item_id,
        product_id: item.product_id,
        product_name: item.product_name,
        quantity: item.quantity,
        return_qty: 0,
        reason: '',
        refund_amount: 0
      }))
      setReturnItems(items)
      
      // Close sales lookup and open returns
      setSalesLookupOpen(false)
      setReturnsOpen(true)
    } catch (error) {
      console.error('Error loading sale details:', error)
      alert('Error loading sale details')
    }
  }

  const updateReturnItem = (index: number, field: string, value: string | number) => {
    setReturnItems(prev => prev.map((item, i) => {
      if (i === index) {
        const updated = { ...item, [field]: value }
        // Calculate refund amount
        if (field === 'return_qty') {
          const price = selectedSale?.items[index]?.price || 0
          const total = price * Number(value)
          updated.refund_amount = total
        }
        return updated
      }
      return item
    }))
    
    // Auto-fill refund amounts when quantity changes
    if (field === 'return_qty') {
      const newTotalRefund = returnItems.reduce((sum, item, i) => {
        if (i === index) {
          const price = selectedSale?.items[index]?.price || 0
          return sum + (price * Number(value))
        }
        return sum + item.refund_amount
      }, 0)
      
      // Auto-fill refund amounts based on method
      if (refundMethod === 'cash') {
        setRefundCash(newTotalRefund)
        setRefundCard(0)
      } else if (refundMethod === 'card') {
        setRefundCash(0)
        setRefundCard(newTotalRefund)
      } else if (refundMethod === 'mixed') {
        // Split 50/50 for mixed payments
        const half = newTotalRefund / 2
        setRefundCash(half)
        setRefundCard(newTotalRefund - half)
      }
    }
  }

  const calculateTotalRefund = () => {
    return returnItems.reduce((sum, item) => sum + item.refund_amount, 0)
  }

  const updateRefundMethod = (method: 'cash' | 'card' | 'mixed') => {
    setRefundMethod(method)
    const totalRefund = calculateTotalRefund()
    
    // Auto-fill refund amounts based on method
    if (method === 'cash') {
      setRefundCash(totalRefund)
      setRefundCard(0)
    } else if (method === 'card') {
      setRefundCash(0)
      setRefundCard(totalRefund)
    } else if (method === 'mixed') {
      // Split 50/50 for mixed payments
      const half = totalRefund / 2
      setRefundCash(half)
      setRefundCard(totalRefund - half)
    }
  }

  const processReturn = async () => {
    const itemsToReturn = returnItems.filter(item => item.return_qty > 0 && item.reason.trim())
    if (itemsToReturn.length === 0) {
      alert('Please select items to return and provide reasons')
      return
    }

    const totalRefund = calculateTotalRefund()
    if (refundMethod === 'cash' && refundCash < totalRefund) {
      alert('Cash refund amount is less than total refund')
      return
    }
    if (refundMethod === 'card' && refundCard < totalRefund) {
      alert('Card refund amount is less than total refund')
      return
    }
    if (refundMethod === 'mixed' && (refundCash + refundCard) < totalRefund) {
      alert('Total refund amount is less than required')
      return
    }

    try {
      const processRes = await apiService.processReturn({
        sale_id: selectedSale!.id,
        items: itemsToReturn.map(item => ({
          sale_item_id: item.sale_item_id,
          product_id: item.product_id,
          quantity: item.return_qty,
          reason: item.reason
        })),
        refund_method: refundMethod,
        refund_cash: refundCash,
        refund_card: refundCard
      })

      // Auto-print the return receipt like the sales invoice
      const receiptId = processRes?.data?.receipt_id ?? processRes?.data?.receipt?.id
      if (receiptId) {
        await printReturnReceipt(receiptId)
      }

      setReturnsOpen(false)
      setSelectedSale(null)
      setReturnItems([])
    } catch (error) {
      console.error('Error processing return:', error)
      alert('Error processing return')
    }
  }

  const printReturnReceipt = async (receiptId: number) => {
    try {
      const res = await apiService.get(`return_receipt.php?receipt_id=${receiptId}`)
      if (!res?.data?.success) return
      const receipt = res.data.receipt

      const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes')
      if (!printWindow) return

      const itemsHTML = (receipt.items || []).map((it: any) => {
        const price = Number(it.price || 0)
        const qty = Number(it.return_qty || 0)
        const line = Number(it.refund_amount ?? (price * qty))
        return `
          <tr>
            <td style="padding: 2px; font-size: 12px;">${it.product_name || it.name || ''}</td>
            <td style="padding: 2px; font-size: 12px; text-align: center;">${qty}</td>
            <td style="padding: 2px; font-size: 12px; text-align: right;">${price.toFixed(2)}</td>
            <td style="padding: 2px; font-size: 12px; text-align: right;">${line.toFixed(2)}</td>
          </tr>
        `
      }).join('')

      const html = `
        <!DOCTYPE html>
        <html>
        <head>
          <title>Return #${receipt.id}</title>
          <style>
            body{font-family: Arial, sans-serif; margin:0; padding:2mm; font-size:11px; line-height:1.2; width:76mm; overflow-x:hidden}
            table{width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed}
            th,td{padding:1px; text-align:left; word-wrap:break-word}
            th:nth-child(1),td:nth-child(1){width:45%}
            th:nth-child(2),td:nth-child(2){width:15%; text-align:center}
            th:nth-child(3),td:nth-child(3){width:20%; text-align:right}
            th:nth-child(4),td:nth-child(4){width:20%; text-align:right}
            @media print{body{margin:0; padding:2mm; width:76mm} @page{margin:0; size:80mm auto}}
          </style>
        </head>
        <body>
          <div style="font-size:14px; line-height:1.3; width:100%; margin:0; padding:0 3mm;">
            <div style="margin-bottom:3px; text-align:center;">
              <h2 style="font-size:18px; margin:0;">RETURN RECEIPT</h2>
              <p style="font-size:12px; margin:2px 0;">Return #${receipt.id}</p>
              ${receipt.sale_id ? `<p style="font-size:12px; margin:2px 0;">Original Sale #${receipt.sale_id}</p>` : ''}
              <p style="font-size:12px; margin:2px 0;">${new Date(receipt.date || Date.now()).toLocaleDateString()} ${new Date(receipt.date || Date.now()).toLocaleTimeString()}</p>
            </div>

            <div style="margin-bottom:3px;">
              <table>
                <thead>
                  <tr style="border-bottom:1px solid #ccc;">
                    <th style="padding:2px; font-size:12px;">Item</th>
                    <th style="padding:2px; font-size:12px;">Qty</th>
                    <th style="padding:2px; font-size:12px;">Price</th>
                    <th style="padding:2px; font-size:12px;">Refund</th>
                  </tr>
                </thead>
                <tbody>
                  ${itemsHTML}
                </tbody>
              </table>
            </div>

            <div style="margin-top:3px; border-top:1px solid #ccc;">
              <table style="width:100%; border-collapse:collapse;">
                <tr>
                  <td style="text-align:left; padding:1px 2px; font-size:12px;">Total Refund:</td>
                  <td style="text-align:right; padding:1px 2px; font-size:12px;">Rs. ${Number(receipt.total_refund || receipt.total || 0).toFixed(2)}</td>
                </tr>
                ${Number(receipt.cash_refund || receipt.cash_amount || 0) > 0 ? `<tr><td style="text-align:left; padding:1px 2px; font-size:12px;">Cash Refund:</td><td style="text-align:right; padding:1px 2px; font-size:12px;">Rs. ${Number(receipt.cash_refund || receipt.cash_amount || 0).toFixed(2)}</td></tr>` : ''}
                ${Number(receipt.card_refund || receipt.card_amount || 0) > 0 ? `<tr><td style="text-align:left; padding:1px 2px; font-size:12px;">Card Refund:</td><td style="text-align:right; padding:1px 2px; font-size:12px;">Rs. ${Number(receipt.card_refund || receipt.card_amount || 0).toFixed(2)}</td></tr>` : ''}
              </table>
            </div>

            <div style="margin-top:3px; text-align:center;">
              <p style="font-size:12px; margin:2px 0;">Thank you!</p>
            </div>
          </div>
          <script>
            window.onload = function(){ window.print(); window.close(); };
          </script>
        </body>
        </html>
      `

      printWindow.document.write(html)
      printWindow.document.close()
    } catch (e) {
      console.error('Failed to print return receipt', e)
    }
  }

  const openPayment = () => {
    setPaymentOpen(true)
    // Reset amounts based on current total (after discount)
    const finalTotal = Math.max(0, total - discountAmount)
    if (paymentMethod === 'cash') { 
      setCashAmount(Number(finalTotal.toFixed(2))); 
      setCardAmount(0) 
    }
    else if (paymentMethod === 'card') { 
      setCashAmount(0); 
      setCardAmount(Number(finalTotal.toFixed(2))) 
    }
    else { 
      const half = Number((finalTotal/2).toFixed(2)); 
      setCashAmount(half); 
      setCardAmount(Number((finalTotal-half).toFixed(2))) 
    }
  }

  const onChangePaymentMethod = (value: PaymentMethod) => {
    setPaymentMethod(value)
    // Reset amounts based on current total (after discount)
    const finalTotal = Math.max(0, total - discountAmount)
    if (value === 'cash') { 
      setCashAmount(Number(finalTotal.toFixed(2))); 
      setCardAmount(0) 
    }
    else if (value === 'card') { 
      setCashAmount(0); 
      setCardAmount(Number(finalTotal.toFixed(2))) 
    }
    else { 
      const half = Number((finalTotal/2).toFixed(2)); 
      setCashAmount(half); 
      setCardAmount(Number((finalTotal-half).toFixed(2))) 
    }
  }

  // Update amounts when discount changes
  useEffect(() => {
    if (paymentOpen) {
      const finalTotal = Math.max(0, total - discountAmount)
      if (paymentMethod === 'cash') { 
        setCashAmount(Number(finalTotal.toFixed(2))); 
        setCardAmount(0) 
      }
      else if (paymentMethod === 'card') { 
        setCashAmount(0); 
        setCardAmount(Number(finalTotal.toFixed(2))) 
      }
      else { 
        const half = Number((finalTotal/2).toFixed(2)); 
        setCashAmount(half); 
        setCardAmount(Number((finalTotal-half).toFixed(2))) 
      }
    }
  }, [discountAmount, total, paymentMethod, paymentOpen])

  const amountsValid = (() => {
    const finalTotal = Math.max(0, total - discountAmount)
    const sum = Number((cashAmount + cardAmount).toFixed(2))
    const due = Number(finalTotal.toFixed(2))
    
    if (paymentMethod === 'cash') return cashAmount >= due && cardAmount === 0
    if (paymentMethod === 'card') return cardAmount >= due && cashAmount === 0
    return Math.abs(sum - due) < 0.01
  })()

  const submitCheckout = async () => {
    if (!amountsValid) {
      alert('Payment amounts do not match the total amount')
      return
    }
    
    if (cart.length === 0) {
      alert('Cart is empty')
      return
    }

    try {
      const finalTotal = Math.max(0, total - discountAmount)
      const saleData = {
        items: cart.map(item => ({
          product_id: item.product.id,
          quantity: item.quantity,
          price: item.product.price,
          tax_rate: item.product.tax_rate || 0
        })),
        total: finalTotal,
        subtotal_amount: subtotal,
        tax_amount: tax,
        discount_amount: discountAmount,
        payment_method: paymentMethod,
        cash_amount: cashAmount,
        card_amount: cardAmount
      }

      const response = await apiService.createSale(saleData)
      
      if (response.data.success) {
        // Clear cart and close payment modal
        setCart([])
        setPaymentOpen(false)
        setCashAmount(0)
        setCardAmount(0)
        setDiscountAmount(0)
        
        // Prepare invoice data
        const invoiceData = {
          id: response.data.sale_id,
          items: cart.map(item => ({
            name: item.product.name,
            quantity: item.quantity,
            price: item.product.price,
            tax_rate: item.product.tax_rate || 0,
            total: item.product.price * item.quantity
          })),
          total: total,
          tax: tax,
          discount: discountAmount,
          final: finalTotal,
          payment_method: paymentMethod,
          cash_amount: cashAmount,
          card_amount: cardAmount,
          balance: Math.max(0, (cashAmount + cardAmount) - finalTotal)
        }
        
        // Show invoice
        setInvoiceData(invoiceData)
        setInvoiceOpen(true)
        
        // Auto-print invoice after a short delay
        setTimeout(() => {
          const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes')
          if (!printWindow) return
          
          const currentDate = new Date().toLocaleDateString()
          const currentTime = new Date().toLocaleTimeString()
          
          let itemsHTML = ''
          invoiceData.items.forEach(item => {
            const itemTotal = item.price * item.quantity
            const taxRate = parseFloat(item.tax_rate.toString()) || 0
            const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal
            const itemTax = itemTotal - itemSubtotal
            
            itemsHTML += `
              <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 2px; font-size: 12px; text-align: left;">${item.name}</td>
                <td style="padding: 2px; font-size: 12px; text-align: center;">${item.quantity}</td>
                <td style="padding: 2px; font-size: 12px; text-align: right;">${item.price.toFixed(2)}</td>
                <td style="padding: 2px; font-size: 12px; text-align: right;">${itemTotal.toFixed(2)}</td>
              </tr>
                              <tr><td colspan="4" style="padding: 1px 2px; font-size: 10px; color: #666; text-align: right;">Tax: Rs. ${itemTax.toFixed(2)} (${taxRate}%)</td></tr>
              <tr><td colspan="4" style="padding: 0; font-size: 10px; color: #666;"><div style="border-bottom: 1px solid #eee; margin: 1px 0;"></div></td></tr>
            `
          })
          
          const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
              <title>Invoice #${invoiceData.id}</title>
              <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 2mm; font-size: 11px; line-height: 1.2; width: 80mm; overflow-x: hidden; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
                th, td { padding: 1px; text-align: left; word-wrap: break-word; }
                th:nth-child(1), td:nth-child(1) { width: 45%; }
                th:nth-child(2), td:nth-child(2) { width: 10%; text-align: center; }
                th:nth-child(3), td:nth-child(3) { width: 22%; text-align: right; }
                th:nth-child(4), td:nth-child(4) { width: 23%; text-align: right; }
                .header { text-align: center; margin-bottom: 5px; }
                .title { font-size: 16px; font-weight: bold; margin: 0; }
                .details { font-size: 10px; margin: 2px 0; }
                .totals { margin-top: 5px; }
                .total-row { font-weight: bold; }
                @media print {
                  body { margin: 0; }
                  .no-print { display: none; }
                }
              </style>
            </head>
            <body>
              <div class="header">
                <h1 class="title">INVOICE</h1>
                <p class="details">Sale #${invoiceData.id}</p>
                <p class="details">${currentDate} ${currentTime}</p>
              </div>
              
              <table>
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  ${itemsHTML}
                </tbody>
              </table>
              
              <div class="totals">
                <table style="width: 100%; border-collapse: collapse;">
                  <tr>
                    <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Subtotal:</td>
                    <td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.total.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Tax:</td>
                    <td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.tax.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Discount:</td>
                    <td style="text-align: right; padding: 1px 2px; font-size: 10px;">-Rs. ${invoiceData.discount.toFixed(2)}</td>
                  </tr>
                  <tr class="total-row">
                    <td style="text-align: left; padding: 1px 2px; font-size: 12px; font-weight: bold;">Total:</td>
                    <td style="text-align: right; padding: 1px 2px; font-size: 12px; font-weight: bold;">Rs. ${invoiceData.final.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Payment:</td>
                    <td style="text-align: right; padding: 1px 2px; font-size: 10px;">${invoiceData.payment_method.toUpperCase()}</td>
                  </tr>
                  ${invoiceData.cash_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Cash:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.cash_amount.toFixed(2)}</td></tr>` : ''}
                  ${invoiceData.card_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Card:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.card_amount.toFixed(2)}</td></tr>` : ''}
                  ${invoiceData.balance > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Change:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.balance.toFixed(2)}</td></tr>` : ''}
                </table>
              </div>
              
              <div style="text-align: center; margin-top: 10px;">
                <p style="font-size: 10px; margin: 2px 0;">Thank you for your purchase!</p>
              </div>
              
              <div class="no-print" style="text-align: center; margin-top: 10px;">
                <button onclick="window.print()">Print Invoice</button>
                <button onclick="window.close()">Close</button>
              </div>
            </body>
            </html>
          `
          
          printWindow.document.write(printContent)
          printWindow.document.close()
          
          // Auto-print after content is loaded
          printWindow.onload = () => {
            printWindow.print()
          }
          
          // Close window after print dialog is closed (whether printed or cancelled)
          const checkClosed = setInterval(() => {
            if (printWindow.closed) {
              clearInterval(checkClosed)
            }
          }, 1000)
          
          // Listen for print events
          printWindow.addEventListener('afterprint', () => {
            printWindow.close()
          })
        }, 500)
        
        // Clear localStorage
        localStorage.removeItem('pos_cart')
      } else {
        alert('Checkout failed: ' + (response.data.error || 'Unknown error'))
      }
    } catch (error: unknown) {
      console.error('Checkout error:', error)
      const errorMessage = error instanceof Error ? error.message : 'Network error'
      alert('Checkout failed: ' + errorMessage)
    }
  }

  const searchIndex = (p: Product) => (
    (p.name || '') + ' ' + (p.sku || '') + ' ' + (p.category_name || '')
  ).toLowerCase()

  const filtered = useMemo(() => {
    const q = (searchTerm || '').toLowerCase().trim()
    if (!q) return products
    return products.filter(p => searchIndex(p).includes(q))
  }, [products, searchTerm])

  const stockClass = (stock: number) => stock < 10 ? 'text-red-600' : 'text-green-600'

  if (loading) {
    return (
      <div className="h-screen flex flex-col overflow-hidden">
        <div className="flex-1 min-h-0">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-3 sm:gap-6 h-full px-2 sm:px-3 py-2 sm:py-3">
            {/* Product Search Skeleton */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5">
              <div className="flex items-center justify-between mb-4">
                <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-32"></div>
                <div className="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
              </div>
              <div className="mb-3 sm:mb-4">
                <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
              </div>
              <div className="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 flex-1">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {[...Array(12)].map((_, index) => (
                    <div key={index} className="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                      <div className="space-y-2">
                        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                        <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-3/4"></div>
                        <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-1/2"></div>
                        <div className="flex items-center justify-between mt-2">
                          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-16"></div>
                          <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-8"></div>
                        </div>
                        <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-12"></div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Shopping Cart Skeleton */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5 relative">
              <div className="absolute top-2 right-2 flex items-center gap-2">
                {[...Array(4)].map((_, index) => (
                  <div key={index} className="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                ))}
              </div>
              
              <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-32 mb-4"></div>
              
              <div className="overflow-y-auto mb-4 flex-1">
                <div className="space-y-3">
                  {[...Array(6)].map((_, index) => (
                    <div key={index} className="border border-gray-200 dark:border-gray-700 rounded p-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3 flex-1">
                          <div className="h-4 w-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                          <div className="space-y-1 flex-1">
                            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-3/4"></div>
                            <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-1/2"></div>
                          </div>
                        </div>
                        <div className="flex items-center space-x-2">
                          <div className="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                          <div className="h-6 w-12 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                          <div className="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              
              <div className="space-y-2">
                <div className="flex justify-between">
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-16"></div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-20"></div>
                </div>
                <div className="flex justify-between">
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-12"></div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-16"></div>
                </div>
                <div className="flex justify-between">
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-20"></div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-24"></div>
                </div>
                <div className="border-t pt-2">
                  <div className="flex justify-between">
                    <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-16"></div>
                    <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-24"></div>
                  </div>
                </div>
              </div>
              
              <div className="mt-4 space-y-2">
                <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="h-screen flex flex-col overflow-hidden">
      <div id="headerSection" className={`flex-shrink-0 ${showHeader ? 'p-3' : 'h-0'}`}></div>
      <div className="flex-1 min-h-0">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-3 sm:gap-6 h-full px-2 sm:px-3 py-2 sm:py-3">
          {/* Product Search and Selection */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5 text-gray-900 dark:text-gray-100">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold text-gray-900"><i className="fas fa-search mr-2"></i>Product Search</h2>
              <button className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white p-2" title="Toggle layout" onClick={() => setSearchLayout(l => l === 'grid' ? 'list' : 'grid')}><i className={`fas ${searchLayout === 'grid' ? 'fa-th-large' : 'fa-list'}`}></i></button>
            </div>
            <div className="mb-3 sm:mb-4">
              <div className="relative">
                <i className="fas fa-search absolute left-2 sm:left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input id="searchInputReact" aria-labelledby="searchInput" type="text" placeholder="Search by product name or SKU..." value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} className="w-full pl-8 sm:pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-400" />
                <span id="searchInput" className="sr-only">Product Search</span>
              </div>
            </div>
            <div id="searchResults" className="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 flex-1 bg-white dark:bg-gray-800">
              {filtered.length === 0 ? (
                <div className="text-gray-500 text-center p-4 text-sm">No products found</div>
              ) : searchLayout === 'grid' ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {filtered.map(product => (
                    <button key={product.id} onClick={() => addToCart(product)} className="p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow cursor-pointer bg-white dark:bg-gray-800 text-left">
                      <div className="flex flex-col gap-1">
                        <h3 className="font-medium text-gray-900 dark:text-gray-100 text-sm line-clamp-2">{product.name}</h3>
                        <div className="text-xs text-gray-500 dark:text-gray-400">SKU: {product.sku || '-'}</div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">{product.category_name || ''}</div>
                      </div>
                      <div className="flex items-center justify-between mt-2">
                        <div className="font-semibold text-gray-900 dark:text-gray-100 text-sm">Rs. {product.price.toFixed(2)}</div>
                        <span className={`text-xs ${stockClass(product.stock)}`}>({product.stock})</span>
                      </div>
                      <div className="text-[10px] text-gray-500 dark:text-gray-400">inc. tax</div>
                    </button>
                  ))}
                </div>
              ) : (
                <div className="divide-y divide-gray-200 dark:divide-gray-700">
                  {filtered.map(product => (
                    <div key={product.id} className="p-2 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 cursor-pointer dark:hover:bg-gray-700" onClick={() => addToCart(product)}>
                      <div className="flex justify-between items-center">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center space-x-1">
                            <h3 className="font-medium text-gray-900 dark:text-gray-100 text-sm truncate">{product.name}</h3>
                            <span className="text-xs text-gray-400">|</span>
                            <span className="text-xs text-gray-500 dark:text-gray-300">{product.sku || '-'}</span>
                            <span className="text-xs text-gray-400">|</span>
                            <span className="text-xs text-gray-500 dark:text-gray-300">{product.category_name || ''}</span>
                          </div>
                        </div>
                        <div className="text-right flex-shrink-0 ml-2">
                          <div className="flex items-center space-x-2">
                            <p className="font-semibold text-gray-900 dark:text-gray-100 text-sm">Rs. {product.price.toFixed(2)}</p>
                            <span className="text-xs text-gray-500 dark:text-gray-300">inc. tax</span>
                            <span className={`text-xs ${stockClass(product.stock)}`}>({product.stock})</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Shopping Cart */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5 relative text-gray-900 dark:text-gray-100 z-0">
            <div className="absolute top-2 right-2 flex items-center gap-2 z-10">
              <button className="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm" title="Show Controls" onClick={() => setControlsOpen(true)}><i className="fas fa-keyboard"></i></button>
              <button className="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded text-sm" title="View Returns" onClick={() => { setSalesLookupOpen(true); loadSalesList(); }}><i className="fas fa-clipboard-list"></i></button>
              <button className="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded text-sm" title="Returns/Exchanges" onClick={() => { setSalesLookupOpen(true); loadSalesList(); }}><i className="fas fa-undo"></i></button>
              <button className="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm" onClick={toggleHeader} title={showHeader ? "Hide Header" : "Show Header"}>
                <i className={`fas ${showHeader ? 'fa-eye-slash' : 'fa-eye'}`}></i>
              </button>
            </div>

            <h2 className="text-lg sm:text-xl font-semibold text-gray-900 mb-3 sm:mb-4"><i className="fas fa-shopping-basket mr-2"></i>Shopping Cart</h2>

            <div id="cartItems" className="overflow-y-auto overflow-x-hidden mb-4 flex-1 min-w-0 max-h-[50vh] md:max-h-[60vh] lg:max-h-none">
              {cart.length === 0 ? (
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-900">
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Sr.</th>
                      <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Item Name</th>
                      <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Desc</th>
                      <th className="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Qty</th>
                      <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Price</th>
                      <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Tax</th>
                      <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Total</th>
                      <th className="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300">Action</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800">
                    <tr>
                      <td colSpan={8} className="py-8 text-center text-gray-500 dark:text-gray-400">Cart is empty</td>
                    </tr>
                  </tbody>
                </table>
              ) : (
                <table className="w-full text-sm table-fixed border border-gray-200 dark:border-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-900 sticky top-0 z-0">
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-8">Sr.</th>
                      <th className="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-2/5">Item Name</th>
                      <th className="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-10">Desc</th>
                      <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-10">Qty</th>
                      <th className="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Price</th>
                      <th className="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Tax</th>
                      <th className="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Total</th>
                      <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-10">Action</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800">
                    {cart.map((item, index) => {
                      const itemTotal = item.product.price * item.quantity
                      const rate = Number(item.product.tax_rate || 0)
                      const itemSubtotal = rate > 0 ? (itemTotal / (1 + rate / 100)) : itemTotal
                      const itemTax = itemTotal - itemSubtotal
                      const isNewlyAdded = newlyAddedItems.has(item.product.id)
                      return (
                        <tr key={item.product.id} className={`border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${isNewlyAdded ? 'bg-yellow-300 dark:bg-amber-700/50 border-yellow-500 dark:border-amber-400' : ''}`}>
                          <td className="py-1 px-1 text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">{index + 1}</td>
                          <td className="py-1 px-1 font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{item.product.name}</td>
                          <td className="py-1 px-1 text-gray-600 dark:text-gray-400 text-xs border-r border-gray-200 dark:border-gray-700 whitespace-nowrap">{rate > 0 ? `${rate}% tax` : 'No tax'}</td>
                          <td className="py-1 px-1 text-center text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{item.quantity}</td>
                          <td className="py-1 px-1 text-right text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{item.product.price.toFixed(2)}</td>
                          <td className="py-1 px-1 text-right text-red-600 dark:text-red-400 border-r border-gray-200 dark:border-gray-700">{itemTax.toFixed(2)}</td>
                          <td className="py-1 px-1 text-right font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{itemTotal.toFixed(2)}</td>
                          <td className="py-1 px-1 text-center">
                            <div className="flex items-center justify-center space-x-0.5">
                              <button onClick={() => updateQuantity(item.product.id, item.quantity - 1)} className="bg-red-500 hover:bg-red-700 text-white p-0.5 rounded text-xs" title="Decrease" aria-label="Decrease"><i className="fas fa-minus text-xs"></i></button>
                              <button onClick={() => updateQuantity(item.product.id, item.quantity + 1)} className="bg-green-500 hover:bg-green-700 text-white p-0.5 rounded text-xs" title="Increase" aria-label="Increase"><i className="fas fa-plus text-xs"></i></button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                  <tfoot className="bg-gray-50 dark:bg-gray-900 border-t-2 border-gray-300 dark:border-gray-700 sticky bottom-0 z-0">
                    <tr className="border-t border-gray-300 dark:border-gray-700">
                      <td colSpan={4} className="py-1 px-1 text-right font-semibold text-gray-800 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700">Total</td>
                      <td className="py-1 px-1 text-right font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{subtotal.toFixed(2)}</td>
                      <td className="py-1 px-1 text-right font-medium text-red-600 dark:text-red-400 border-r border-gray-200 dark:border-gray-700">{tax.toFixed(2)}</td>
                      <td className="py-1 px-1 text-right font-bold text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">{total.toFixed(2)}</td>
                      <td className="py-1 px-1"></td>
                    </tr>
                  </tfoot>
                </table>
              )}
            </div>

            <div className="border-t pt-2 flex-shrink-0">
              <div className="flex justify-between items-center mb-2">
                <span className="text-base font-semibold text-gray-900">Final Total:</span>
                <span id="finalTotal" className="text-lg sm:text-xl font-bold text-blue-600">Rs. {total.toFixed(2)}</span>
              </div>
              <div className="flex flex-wrap items-center gap-2 mb-2 min-w-0">
                <button onClick={openParkModal} disabled={cart.length === 0} className="shrink-0 whitespace-nowrap bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-400 disabled:hover:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold py-3 px-4 rounded text-base"><i className="fas fa-inbox mr-2"></i>Park Sale</button>
                <button onClick={() => setParkedOpen(true)} className="shrink-0 whitespace-nowrap bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded text-base"><i className="fas fa-folder-open mr-2"></i>Resume</button>
                <button onClick={clearCart} className="shrink-0 whitespace-nowrap bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded text-base"><i className="fas fa-trash mr-2"></i>Clear Cart</button>
                <button onClick={openPayment} disabled={cart.length === 0} className="flex-1 min-w-0 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded text-base"><i className="fas fa-credit-card mr-2"></i>Proceed to Payment</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Payment Modal */}
      {paymentOpen && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setPaymentOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <i className="fas fa-money-bill-wave mr-2"></i>Payment Details
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Left Column - Payment Inputs */}
                <div>
                  {/* Payment Method */}
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method</label>
                    <select value={paymentMethod} onChange={(e) => onChangePaymentMethod(e.target.value as PaymentMethod)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100">
                      <option value="cash">Cash Only</option>
                      <option value="card">Card Only</option>
                      <option value="mixed">Cash + Card</option>
                    </select>
                  </div>
                  
                  {/* Cash Amount */}
                  <div id="cashSection" className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cash Amount (Rs.)</label>
                    <input type="number" step="0.01" min="0" value={cashAmount} onChange={(e) => setCashAmount(Number(e.target.value) || 0)} 
                           className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100" />
                  </div>
                  
                  {/* Card Amount */}
                  <div id="cardSection" className={`mb-4 ${paymentMethod === 'mixed' ? '' : 'hidden'}`}>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Card Amount (Rs.)</label>
                    <input type="number" step="0.01" min="0" value={cardAmount} onChange={(e) => setCardAmount(Number(e.target.value) || 0)} 
                           className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100" />
                  </div>
                  
                  {/* Discount */}
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      <i className="fas fa-percentage mr-1"></i>Discount (Rs.)
                    </label>
                    <input type="number" step="0.01" min="0" value={discountAmount} onChange={(e) => setDiscountAmount(Number(e.target.value) || 0)} 
                           className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100" />
                  </div>
                  
                  {/* Balance/Change */}
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Balance/Change</label>
                    <input type="text" value={`Rs. ${Math.max(0, (cashAmount + cardAmount) - Math.max(0, total - discountAmount)).toFixed(2)}`} readOnly 
                           className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-600 dark:text-gray-100 text-gray-900 dark:text-gray-100" />
                  </div>
                </div>
                
                {/* Right Column - Summary */}
                <div>
                  {/* Summary */}
                  <div className="p-4 bg-gray-50 dark:bg-gray-700 rounded-md h-full">
                    <h4 className="font-medium text-gray-900 dark:text-gray-100 mb-3">Payment Summary</h4>
                    <div className="text-sm space-y-2">
                      <div className="flex justify-between">
                        <span className="text-gray-700 dark:text-gray-300">Subtotal:</span>
                        <span className="text-gray-900 dark:text-gray-100">Rs. {subtotal.toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-700 dark:text-gray-300">Tax:</span>
                        <span className="text-gray-900 dark:text-gray-100">Rs. {tax.toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-700 dark:text-gray-300">Total:</span>
                        <span className="text-gray-900 dark:text-gray-100">Rs. {total.toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-700 dark:text-gray-300">Discount:</span>
                        <span className="text-red-600 dark:text-red-400">-Rs. {discountAmount.toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between font-semibold border-t border-gray-300 dark:border-gray-600 pt-2 text-base">
                        <span className="text-gray-900 dark:text-gray-100">Final Total:</span>
                        <span className="text-gray-900 dark:text-gray-100">Rs. {Math.max(0, total - discountAmount).toFixed(2)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="flex justify-end space-x-4 mt-6">
                <button onClick={() => setPaymentOpen(false)} className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                  <i className="fas fa-times mr-2"></i>Cancel
                </button>
                <button onClick={submitCheckout} disabled={!amountsValid} className="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:hover:bg-gray-400 text-white font-bold py-2 px-4 rounded">
                  <i className="fas fa-check mr-2"></i>Complete Sale
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Park Note Modal */}
      {parkNoteOpen && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setParkNoteOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-sm shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i className="fas fa-sticky-note mr-2"></i>Park Sale Note</h3>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Note (required)</label>
            <input type="text" value={parkNote} onChange={(e) => setParkNote(e.target.value)} required placeholder="Customer name or reference" className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 mb-4" />
            <div className="flex justify-end gap-2">
              <button className="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" onClick={() => setParkNoteOpen(false)}><i className="fas fa-times mr-1"></i>Cancel</button>
              <button className="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded" onClick={parkCart}><i className="fas fa-inbox mr-1"></i>Park</button>
            </div>
          </div>
        </div>
      )}

      {/* Parked Sales Modal */}
      {parkedOpen && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setParkedOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i className="fas fa-folder-tree mr-2"></i>Parked Sales</h3>
            <div className="mb-3">
              <input type="text" placeholder="Search notes or IDs..." className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100" />
            </div>
            <div className="border border-gray-200 dark:border-gray-700 rounded p-2 max-h-80 overflow-y-auto text-sm">
              {parkedCarts.length === 0 ? (
                <div className="text-gray-500 dark:text-gray-400">No parked sales</div>
              ) : (
                <div className="space-y-2">
                  {parkedCarts.map(entry => (
                    <div key={entry.id} className="border border-gray-200 dark:border-gray-700 rounded p-2 flex items-center justify-between">
                      <div className="min-w-0">
                        <div className="font-medium text-gray-900 dark:text-gray-100 truncate">{entry.note || 'No note'}</div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">{new Date(entry.createdAt).toLocaleString()} • {entry.cart.length} items</div>
                      </div>
                      <div className="flex items-center gap-2">
                        <button className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-xs" onClick={() => resumeFromEntry(entry)}><i className="fas fa-folder-open mr-1"></i>Resume</button>
                        <button className="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs" onClick={() => deleteParked(entry)}><i className="fas fa-trash mr-1"></i>Delete</button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="flex justify-end space-x-2 mt-4">
              <button onClick={() => setParkedOpen(false)} className="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded"><i className="fas fa-times mr-1"></i>Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Controls Modal */}
      {controlsOpen && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setControlsOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i className="fas fa-keyboard mr-2"></i>Sales Controls</h3>
            <div className="text-sm grid grid-cols-1 gap-2">
              <div><span className="font-semibold">F2 / Ctrl+F</span> – Focus Search</div>
              <div><span className="font-semibold">Alt+P</span> – Proceed to Payment</div>
              <div><span className="font-semibold">Alt+K</span> – Park Sale</div>
              <div><span className="font-semibold">Alt+R</span> – Open Resume</div>
              <div><span className="font-semibold">Alt+C</span> – Clear Cart</div>
              <div><span className="font-semibold">+</span> – Increase last item qty</div>
              <div><span className="font-semibold">-</span> – Decrease last item qty</div>
              <div><span className="font-semibold">Delete</span> – Remove last item</div>
            </div>
            <div className="flex justify-end mt-4">
              <button className="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" onClick={() => setControlsOpen(false)}><i className="fas fa-times mr-1"></i>Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Sales Lookup Modal */}
      {salesLookupOpen && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setSalesLookupOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-[95vw] max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel max-h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i className="fas fa-receipt mr-2"></i>Select a Sale</h3>
            <div className="mb-3">
              <div className="relative">
                <i className="fas fa-search absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="Search by Sale ID, cashier, or method..." className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100" />
              </div>
            </div>
            <div className="border border-gray-200 dark:border-gray-700 rounded p-2 overflow-y-auto text-sm flex-1 min-h-0">
              {salesList.length === 0 ? (
                <div className="text-gray-500 dark:text-gray-400">Loading...</div>
              ) : (
                <div className="space-y-2">
                  {salesList.map(sale => (
                    <div key={sale.id} className="border border-gray-200 dark:border-gray-700 rounded p-2 flex items-center justify-between">
                      <div>
                        <div className="font-medium text-gray-900 dark:text-gray-100">Sale #{sale.id}</div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">{new Date(sale.date).toLocaleString()} • Rs. {sale.total_amount.toFixed(2)}</div>
                      </div>
                      <button 
                        className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs"
                        onClick={() => loadSaleForReturn(sale.id)}
                      >
                        <i className="fas fa-undo mr-1"></i>Return
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="flex justify-end gap-2 mt-4">
              <button className="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded" onClick={() => setSalesLookupOpen(false)}><i className="fas fa-times mr-1"></i>Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Returns Modal */}
      {returnsOpen && selectedSale && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setReturnsOpen(false) }}>
          <div className="relative mx-auto p-5 border w-[95vw] max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel max-h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
              <i className="fas fa-undo mr-2"></i>Returns / Exchanges - Sale #{selectedSale.id}
            </h3>
            
            <div className="mb-3 flex items-center gap-2">
              <div className="text-sm text-gray-700 dark:text-gray-300">Sale Date: {new Date(selectedSale.date).toLocaleDateString()}</div>
                              <div className="text-sm text-gray-700 dark:text-gray-300">Total: Rs. {selectedSale.total_amount.toFixed(2)}</div>
            </div>
            
            <div className="border border-gray-200 dark:border-gray-700 rounded mb-3 flex-1 min-h-0 overflow-y-auto">
              <table className="w-full text-sm table-fixed">
                <thead className="bg-gray-50 dark:bg-gray-900">
                  <tr className="border-b border-gray-200 dark:border-gray-700">
                    <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-1/3 text-xs whitespace-nowrap">Item</th>
                    <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Sold</th>
                    <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Ret.</th>
                    <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Rem.</th>
                    <th className="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-20 text-xs whitespace-nowrap">Return Qty</th>
                    <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-56 text-xs whitespace-nowrap">Reason</th>
                    <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-24 text-xs whitespace-nowrap">Refund</th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-800">
                  {returnItems.map((item, index) => (
                    <tr key={index} className="border-b border-gray-200 dark:border-gray-700">
                      <td className="py-1 px-2 text-gray-900 dark:text-gray-100 text-xs">{item.product_name}</td>
                      <td className="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs">{item.quantity}</td>
                      <td className="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs">{selectedSale.items[index]?.returned_qty || 0}</td>
                      <td className="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs">{item.quantity - (selectedSale.items[index]?.returned_qty || 0)}</td>
                      <td className="py-1 px-1 text-center">
                        <input 
                          type="number" 
                          min="0" 
                          max={item.quantity - (selectedSale.items[index]?.returned_qty || 0)}
                          value={item.return_qty}
                          onChange={(e) => {
                            const maxQty = item.quantity - (selectedSale.items[index]?.returned_qty || 0);
                            const inputQty = Number(e.target.value) || 0;
                            const validQty = Math.min(Math.max(0, inputQty), maxQty);
                            updateReturnItem(index, 'return_qty', validQty);
                          }}
                          className="w-full px-1 py-0.5 border border-gray-300 rounded text-xs bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100"
                        />
                      </td>
                      <td className="py-1 px-2">
                        <input 
                          type="text" 
                          value={item.reason}
                          onChange={(e) => updateReturnItem(index, 'reason', e.target.value)}
                          placeholder="Reason for return"
                          className="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100"
                        />
                      </td>
                      <td className="py-1 px-2 text-right text-gray-900 dark:text-gray-100 text-xs">Rs. {item.refund_amount.toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-gray-50 dark:bg-gray-900 sticky bottom-0 z-10 border-t-2 border-gray-300 dark:border-gray-700">
                  <tr className="border-t border-gray-200 dark:border-gray-700">
                    <td colSpan={6} className="py-2 px-2 text-right font-semibold text-gray-800 dark:text-gray-200">Total Refund</td>
                    <td className="py-2 px-2 text-right font-bold text-gray-900 dark:text-gray-100">Rs. {calculateTotalRefund().toFixed(2)}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            {/* Refund method & amounts */}
            <div className="border-t pt-3 mb-3">
              <div className="flex items-center justify-between mb-2">
                <div className="text-sm font-medium text-gray-900 dark:text-gray-100">Refund</div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Refund Method</label>
                  <select 
                    value={refundMethod} 
                    onChange={(e) => updateRefundMethod(e.target.value as 'cash' | 'card' | 'mixed')}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100"
                  >
                    <option value="cash">Cash Only</option>
                    <option value="card">Card Only</option>
                    <option value="mixed">Cash + Card</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cash Refund (Rs.)</label>
                  <input 
                    type="number" 
                    step="0.01" 
                    min="0" 
                    value={refundCash} 
                    onChange={(e) => setRefundCash(Number(e.target.value) || 0)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100" 
                  />
                </div>
                <div className={refundMethod === 'mixed' ? '' : 'hidden'}>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Card Refund (Rs.)</label>
                  <input 
                    type="number" 
                    step="0.01" 
                    min="0" 
                    value={refundCard} 
                    onChange={(e) => setRefundCard(Number(e.target.value) || 0)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100 text-gray-900 dark:text-gray-100" 
                  />
                </div>
              </div>
                              <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">Total refund must equal: <span className="font-semibold">Rs. {calculateTotalRefund().toFixed(2)}</span></div>
            </div>
            
            <div className="flex justify-between items-center">
              <div className="text-xs text-gray-500 dark:text-gray-400">Refund will be issued to original tender (cash/card split as per sale).</div>
              <div className="flex gap-2">
                <button className="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded" onClick={() => setReturnsOpen(false)}><i className="fas fa-times mr-1"></i>Close</button>
                <button 
                  className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded disabled:bg-gray-400" 
                  disabled={returnItems.filter(item => item.return_qty > 0 && item.reason.trim()).length === 0}
                  onClick={processReturn}
                >
                  <i className="fas fa-check mr-1"></i>Process Return
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Invoice Modal */}
      {invoiceOpen && invoiceData && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setInvoiceOpen(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="text-left" style={{fontSize: '14px', lineHeight: '1.3', width: '100%', margin: 0, padding: '0 3mm'}}>
              <div className="text-center mb-1" style={{marginBottom: '3px'}}>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100" style={{fontSize: '18px', margin: 0}}>INVOICE</h2>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>Sale #{invoiceData.id}</p>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>{new Date().toLocaleDateString()} {new Date().toLocaleTimeString()}</p>
              </div>
              
              <div className="mb-1" style={{marginBottom: '3px'}}>
                <table className="w-full text-xs" style={{fontSize: '12px', margin: '2px 0', tableLayout: 'fixed'}}>
                  <thead>
                    <tr className="border-b border-gray-300 dark:border-gray-600">
                      <th className="py-1 text-left text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '45%'}}>Item</th>
                      <th className="py-1 text-center text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '10%'}}>Qty</th>
                      <th className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '22%'}}>Price</th>
                      <th className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '23%'}}>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {invoiceData.items.map((item, index) => {
                      const itemTotal = item.price * item.quantity
                      const taxRate = parseFloat(item.tax_rate.toString()) || 0
                      const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal
                      const itemTax = itemTotal - itemSubtotal
                      
                      return (
                        <React.Fragment key={index}>
                          <tr className="border-b border-gray-200 dark:border-gray-700">
                            <td className="py-1 text-left text-xs text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{item.name}</td>
                            <td className="py-1 text-center text-xs text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{item.quantity}</td>
                            <td className="py-1 text-right text-xs text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{item.price.toFixed(2)}</td>
                            <td className="py-1 text-right text-xs text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{itemTotal.toFixed(2)}</td>
                          </tr>
                          <tr>
                            <td colSpan={4} className="py-0 text-right text-xs text-gray-600 dark:text-gray-400" style={{padding: '1px 2px', fontSize: '10px'}}>
                              Tax: Rs. {itemTax.toFixed(2)} ({taxRate}%)
                            </td>
                          </tr>
                          <tr>
                            <td colSpan={4} className="py-0" style={{padding: 0, fontSize: '10px'}}>
                              <div className="border-b border-gray-200 dark:border-gray-600" style={{margin: '1px 0'}}></div>
                            </td>
                          </tr>
                        </React.Fragment>
                      )
                    })}
                  </tbody>
                </table>
              </div>
              
              <div className="border-t border-gray-300 dark:border-gray-600 pt-1" style={{marginTop: '3px'}}>
                <table style={{width: '100%', borderCollapse: 'collapse'}}>
                  <tr>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Subtotal:</td>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>Rs. {invoiceData.total.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Tax:</td>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>Rs. {invoiceData.tax.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Discount:</td>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>-Rs. {invoiceData.discount.toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td className="text-gray-900 dark:text-gray-100 font-bold" style={{textAlign: 'left', padding: '1px 2px', fontSize: '14px'}}>Total:</td>
                    <td className="text-gray-900 dark:text-gray-100 font-bold" style={{textAlign: 'right', padding: '1px 2px', fontSize: '14px'}}>Rs. {invoiceData.final.toFixed(2)}</td>
                  </tr>
                </table>
              </div>
              
              <div className="border-t border-gray-300 dark:border-gray-600 pt-1 mt-1" style={{marginTop: '3px'}}>
                <table style={{width: '100%', borderCollapse: 'collapse'}}>
                  <tr>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Payment Method:</td>
                    <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>{invoiceData.payment_method.toUpperCase()}</td>
                  </tr>
                  {invoiceData.cash_amount > 0 && (
                    <tr>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Cash:</td>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>Rs. {invoiceData.cash_amount.toFixed(2)}</td>
                    </tr>
                  )}
                  {invoiceData.card_amount > 0 && (
                    <tr>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Card:</td>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>Rs. {invoiceData.card_amount.toFixed(2)}</td>
                    </tr>
                  )}
                  {invoiceData.balance > 0 && (
                    <tr>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Change:</td>
                      <td className="text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>Rs. {invoiceData.balance.toFixed(2)}</td>
                    </tr>
                  )}
                </table>
              </div>
              
              <div className="text-center mt-1" style={{marginTop: '3px'}}>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>Thank you for your purchase!</p>
              </div>
            </div>
            
            <div className="flex justify-end space-x-4 mt-6">
              <button onClick={() => setInvoiceOpen(false)} className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i className="fas fa-times mr-2"></i>Close
              </button>
              <button onClick={() => {
                const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes')
                if (!printWindow || !invoiceData) return
                
                const currentDate = new Date().toLocaleDateString()
                const currentTime = new Date().toLocaleTimeString()
                
                let itemsHTML = ''
                invoiceData.items.forEach(item => {
                  const itemTotal = item.price * item.quantity
                  const taxRate = parseFloat(item.tax_rate.toString()) || 0
                  const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal
                  const itemTax = itemTotal - itemSubtotal
                  
                  itemsHTML += `
                    <tr style="border-bottom: 1px solid #ddd;">
                      <td style="padding: 2px; font-size: 12px; text-align: left;">${item.name}</td>
                      <td style="padding: 2px; font-size: 12px; text-align: center;">${item.quantity}</td>
                      <td style="padding: 2px; font-size: 12px; text-align: right;">${item.price.toFixed(2)}</td>
                      <td style="padding: 2px; font-size: 12px; text-align: right;">${itemTotal.toFixed(2)}</td>
                    </tr>
                    <tr><td colspan="4" style="padding: 1px 2px; font-size: 10px; color: #666; text-align: right;">Tax: Rs. ${itemTax.toFixed(2)} (${taxRate}%)</td></tr>
                    <tr><td colspan="4" style="padding: 0; font-size: 10px; color: #666;"><div style="border-bottom: 1px solid #eee; margin: 1px 0;"></div></td></tr>
                  `
                })
                
                const printContent = `
                  <!DOCTYPE html>
                  <html>
                  <head>
                    <title>Invoice #${invoiceData.id}</title>
                    <style>
                      body { font-family: Arial, sans-serif; margin: 0; padding: 2mm; font-size: 11px; line-height: 1.2; width: 80mm; overflow-x: hidden; }
                      table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
                      th, td { padding: 1px; text-align: left; word-wrap: break-word; }
                      th:nth-child(1), td:nth-child(1) { width: 45%; }
                      th:nth-child(2), td:nth-child(2) { width: 10%; text-align: center; }
                      th:nth-child(3), td:nth-child(3) { width: 22%; text-align: right; }
                      th:nth-child(4), td:nth-child(4) { width: 23%; text-align: right; }
                      .header { text-align: center; margin-bottom: 5px; }
                      .title { font-size: 16px; font-weight: bold; margin: 0; }
                      .details { font-size: 10px; margin: 2px 0; }
                      .totals { margin-top: 5px; }
                      .total-row { font-weight: bold; }
                      @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                      }
                    </style>
                  </head>
                  <body>
                    <div class="header">
                      <h1 class="title">INVOICE</h1>
                      <p class="details">Sale #${invoiceData.id}</p>
                      <p class="details">${currentDate} ${currentTime}</p>
                    </div>
                    
                    <table>
                      <thead>
                        <tr>
                          <th>Item</th>
                          <th>Qty</th>
                          <th>Price</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${itemsHTML}
                      </tbody>
                    </table>
                    
                    <div class="totals">
                      <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                          <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Subtotal:</td>
                          <td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.total.toFixed(2)}</td>
                        </tr>
                        <tr>
                          <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Tax:</td>
                          <td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.tax.toFixed(2)}</td>
                        </tr>
                        <tr>
                          <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Discount:</td>
                          <td style="text-align: right; padding: 1px 2px; font-size: 10px;">-Rs. ${invoiceData.discount.toFixed(2)}</td>
                        </tr>
                        <tr class="total-row">
                          <td style="text-align: left; padding: 1px 2px; font-size: 12px; font-weight: bold;">Total:</td>
                          <td style="text-align: right; padding: 1px 2px; font-size: 12px; font-weight: bold;">Rs. ${invoiceData.final.toFixed(2)}</td>
                        </tr>
                        <tr>
                          <td style="text-align: left; padding: 1px 2px; font-size: 10px;">Payment:</td>
                          <td style="text-align: right; padding: 1px 2px; font-size: 10px;">${invoiceData.payment_method.toUpperCase()}</td>
                        </tr>
                                          ${invoiceData.cash_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Cash:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.cash_amount.toFixed(2)}</td></tr>` : ''}
                  ${invoiceData.card_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Card:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.card_amount.toFixed(2)}</td></tr>` : ''}
                  ${invoiceData.balance > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 10px;">Change:</td><td style="text-align: right; padding: 1px 2px; font-size: 10px;">Rs. ${invoiceData.balance.toFixed(2)}</td></tr>` : ''}
                      </table>
                    </div>
                    
                    <div style="text-align: center; margin-top: 10px;">
                      <p style="font-size: 10px; margin: 2px 0;">Thank you for your purchase!</p>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 10px;">
                      <button onclick="window.print()">Print Invoice</button>
                      <button onclick="window.close()">Close</button>
                    </div>
                  </body>
                  </html>
                `
                
                printWindow.document.write(printContent)
                printWindow.document.close()
                
                // Auto-print after content is loaded
                printWindow.onload = () => {
                  printWindow.print()
                }
                
                // Close window after print dialog is closed (whether printed or cancelled)
                const checkClosed = setInterval(() => {
                  if (printWindow.closed) {
                    clearInterval(checkClosed)
                  }
                }, 1000)
                
                // Listen for print events
                printWindow.addEventListener('afterprint', () => {
                  printWindow.close()
                })
              }} className="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                <i className="fas fa-print mr-2"></i>Print
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
