import React from 'react'
import { useSettings } from '../contexts/SettingsContext'

interface PreviewItem {
  name: string
  qty: number
  price: number
  taxRate: number
}

const sampleItems: PreviewItem[] = [
  { name: 'Sample Item A', qty: 1, price: 450, taxRate: 15 },
  { name: 'Sample Item B longer name', qty: 2, price: 150, taxRate: 0 },
]

const currency = (v: number) => `Rs. ${v.toFixed(2)}`

const CompactPreview: React.FC = () => {
  const { business } = useSettings()
  // Per item tax (price considered tax-inclusive for preview, like sales)
  const itemCalcs = sampleItems.map(i => {
    const itemTotal = i.qty * i.price
    const rate = i.taxRate || 0
    const itemSubtotal = rate > 0 ? (itemTotal / (1 + rate / 100)) : itemTotal
    const itemTax = itemTotal - itemSubtotal
    return { ...i, itemTotal, itemSubtotal, itemTax }
  })
  const subtotal = itemCalcs.reduce((s, it) => s + it.itemSubtotal, 0)
  const tax = itemCalcs.reduce((s, it) => s + it.itemTax, 0)
  const total = itemCalcs.reduce((s, it) => s + it.itemTotal, 0)
  // Sample paid amounts for preview (adjust as needed later)
  const paidCash = total
  const paidCard = 0
  const change = paidCash + paidCard - total

  const now = new Date()
  const dateStr = now.toLocaleDateString()
  const timeStr = now.toLocaleTimeString()

  return (
    <div
      style={{
        backgroundColor: '#ffffff',
        color: '#000000',
        width: '76mm',
        padding: '2mm',
        fontFamily: 'Arial, sans-serif',
        fontSize: '11px',
        lineHeight: 1.2,
        border: '1px solid #e5e7eb',
        borderRadius: '6px'
      }}
    >
      <div style={{ textAlign: 'center', marginBottom: '3px' }}>
        {business.logoUrl && (
          <img src={business.logoUrl} alt="Logo" style={{ height: 72, width: 72, objectFit: 'contain', margin: '0 auto 2px auto' }} />
        )}
        <h2 style={{ fontSize: '18px', margin: 0, fontWeight: 700 }}>INVOICE</h2>
        <p style={{ fontSize: '12px', margin: '2px 0' }}>Sale #123</p>
        <p style={{ fontSize: '12px', margin: '2px 0' }}>{dateStr} {timeStr}</p>
      </div>

      <div style={{ textAlign: 'center', marginBottom: '3px' }}>
        <div style={{ fontWeight: 700 }}>{business.name}</div>
        <div style={{ fontSize: '10px', color: '#6b7280' }}>{business.address}</div>
        <div style={{ fontSize: '10px', color: '#6b7280' }}>{business.phone}</div>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '10px', tableLayout: 'fixed' }}>
        <thead>
          <tr style={{ borderBottom: '1px solid #cccccc' }}>
            <th style={{ padding: '2px', width: '45%', textAlign: 'left' }}>Item</th>
            <th style={{ padding: '2px', width: '15%', textAlign: 'center' }}>Qty</th>
            <th style={{ padding: '2px', width: '20%', textAlign: 'right' }}>Price</th>
            <th style={{ padding: '2px', width: '20%', textAlign: 'right' }}>Total</th>
          </tr>
        </thead>
        <tbody>
          {itemCalcs.map((i, idx) => (
            <React.Fragment key={idx}>
              <tr>
                <td style={{ padding: '2px', textAlign: 'left' }}>{i.name}</td>
                <td style={{ padding: '2px', textAlign: 'center' }}>{i.qty}</td>
                <td style={{ padding: '2px', textAlign: 'right' }}>{currency(i.price)}</td>
                <td style={{ padding: '2px', textAlign: 'right' }}>{currency(i.itemTotal)}</td>
              </tr>
              <tr>
                <td colSpan={4} style={{ padding: '1px 2px', fontSize: '10px', color: '#666666', textAlign: 'right' }}>Tax: {currency(i.itemTax)} ({i.taxRate || 0}%)</td>
              </tr>
              <tr style={{ height: '2px' }}>
                <td colSpan={4} style={{ borderBottom: '1px solid #dddddd', padding: 0 }} />
              </tr>
            </React.Fragment>
          ))}
        </tbody>
      </table>

      <div style={{ marginTop: '3px', borderTop: '1px solid #cccccc' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <tbody>
            <tr>
              <td style={{ textAlign: 'left', padding: '1px 2px' }}>Subtotal:</td>
              <td style={{ textAlign: 'right', padding: '1px 2px' }}>{currency(subtotal)}</td>
            </tr>
            <tr>
              <td style={{ textAlign: 'left', padding: '1px 2px' }}>Tax:</td>
              <td style={{ textAlign: 'right', padding: '1px 2px' }}>{currency(tax)}</td>
            </tr>
            <tr>
              <td style={{ textAlign: 'left', padding: '1px 2px', fontWeight: 700 }}>Total:</td>
              <td style={{ textAlign: 'right', padding: '1px 2px', fontWeight: 700 }}>{currency(total)}</td>
            </tr>
            <tr>
              <td style={{ textAlign: 'left', padding: '1px 2px' }}>Change:</td>
              <td style={{ textAlign: 'right', padding: '1px 2px' }}>{currency(change)}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style={{ marginTop: '3px', textAlign: 'center' }}>
        <p style={{ fontSize: '12px', margin: '2px 0', color: '#6b7280' }}>Thank you for your purchase!</p>
      </div>
    </div>
  )
}

const DetailedPreview: React.FC = () => {
  const { business } = useSettings()
  const itemCalcs = sampleItems.map(i => {
    const itemTotal = i.qty * i.price
    const rate = i.taxRate || 0
    const itemSubtotal = rate > 0 ? (itemTotal / (1 + rate / 100)) : itemTotal
    const itemTax = itemTotal - itemSubtotal
    return { ...i, itemTotal, itemSubtotal, itemTax }
  })
  const subtotal = itemCalcs.reduce((s, it) => s + it.itemSubtotal, 0)
  const tax = itemCalcs.reduce((s, it) => s + it.itemTax, 0)
  const discount = 0
  const total = itemCalcs.reduce((s, it) => s + it.itemTotal, 0) - discount
  const paidCash = total
  const paidCard = 0
  const change = paidCash + paidCard - total
  const now = new Date()
  const dateStr = now.toLocaleDateString()
  const timeStr = now.toLocaleTimeString()
  return (
    <div className="w-[520px] border border-gray-200 rounded p-4 text-sm" style={{ backgroundColor: '#ffffff', color: '#000000' }}>
      {/* Match compact header details */}
      <div style={{ textAlign: 'center', marginBottom: '8px' }}>
        <h2 style={{ fontSize: '18px', margin: 0, fontWeight: 700 }}>INVOICE</h2>
        <p style={{ fontSize: '12px', margin: '2px 0' }}>Sale #123</p>
        <p style={{ fontSize: '12px', margin: '2px 0' }}>{dateStr} {timeStr}</p>
      </div>
      <div className="flex items-center justify-between mb-3">
        <div>
          <div className="text-xl font-bold" style={{ color: '#111827' }}>{business.name}</div>
          <div className="text-xs" style={{ color: '#6b7280' }}>{business.address}</div>
          <div className="text-xs" style={{ color: '#6b7280' }}>{business.phone}</div>
        </div>
        {business.logoUrl && (
          <img src={business.logoUrl} alt="Logo" className="h-24 w-24 object-contain" />
        )}
      </div>
      <table className="w-full text-xs mb-3">
        <thead className="bg-gray-200">
          <tr>
            <th className="text-left p-1">Item</th>
            <th className="text-center p-1">Qty</th>
            <th className="text-right p-1">Price</th>
            <th className="text-right p-1">Total</th>
          </tr>
        </thead>
        <tbody>
          {itemCalcs.map((i, idx) => (
            <React.Fragment key={idx}>
              <tr className="border-b border-gray-100">
                <td className="p-1">{i.name}</td>
                <td className="p-1 text-center">{i.qty}</td>
                <td className="p-1 text-right">{currency(i.price)}</td>
                <td className="p-1 text-right">{currency(i.itemTotal)}</td>
              </tr>
              <tr>
                <td colSpan={4} className="text-right" style={{ padding: '1px 4px', fontSize: '10px', color: '#666666' }}>Tax: {currency(i.itemTax)} ({i.taxRate || 0}%)</td>
              </tr>
            </React.Fragment>
          ))}
        </tbody>
      </table>
      <div className="grid grid-cols-2 gap-2">
        <div />
        <div className="space-y-1">
          <div className="flex justify-between"><span>Subtotal</span><span>{currency(subtotal)}</span></div>
          <div className="flex justify-between"><span>Tax (15%)</span><span>{currency(tax)}</span></div>
          <div className="flex justify-between"><span>Discount</span><span>-{currency(discount)}</span></div>
          <div className="flex justify-between font-semibold"><span>Total</span><span>{currency(total)}</span></div>
          <div className="flex justify-between"><span>Change</span><span>{currency(change)}</span></div>
        </div>
      </div>
      <div style={{ marginTop: '6px', textAlign: 'center' }}>
        <p style={{ fontSize: '12px', margin: 0, color: '#6b7280' }}>Thank you for your purchase!</p>
      </div>
    </div>
  )
}

const SimplePreview: React.FC = () => {
  const itemCalcs = sampleItems.map(i => {
    const itemTotal = i.qty * i.price
    const rate = i.taxRate || 0
    const itemSubtotal = rate > 0 ? (itemTotal / (1 + rate / 100)) : itemTotal
    const itemTax = itemTotal - itemSubtotal
    return { ...i, itemTotal, itemSubtotal, itemTax }
  })
  const subtotal = itemCalcs.reduce((s, it) => s + it.itemSubtotal, 0)
  const tax = itemCalcs.reduce((s, it) => s + it.itemTax, 0)
  const total = itemCalcs.reduce((s, it) => s + it.itemTotal, 0)
  const paidCash = total
  const paidCard = 0
  const change = paidCash + paidCard - total
  return (
    <div className="w-[320px] border border-gray-200 rounded p-3 text-sm" style={{ backgroundColor: '#ffffff', color: '#000000' }}>
      <div className="font-bold text-center mb-2">Invoice</div>
      {itemCalcs.map((i, idx) => (
        <div key={idx} className="text-xs">
          <div className="flex justify-between"><span className="truncate">{i.name} x {i.qty}</span><span>{currency(i.itemTotal)}</span></div>
          <div className="text-right" style={{ fontSize: '10px', color: '#666666' }}>Tax: {currency(i.itemTax)} ({i.taxRate || 0}%)</div>
        </div>
      ))}
      <div className="border-t border-gray-200 my-2" />
      <div className="flex justify-between"><span>Subtotal</span><span>{currency(subtotal)}</span></div>
      <div className="flex justify-between"><span>Tax</span><span>{currency(tax)}</span></div>
      <div className="flex justify-between font-semibold"><span>Total</span><span>{currency(total)}</span></div>
      <div className="flex justify-between"><span>Change</span><span>{currency(change)}</span></div>
    </div>
  )
}

const InvoicePreview: React.FC = () => {
  const { receiptTemplate } = useSettings()
  if (receiptTemplate === 'detailed') return <DetailedPreview />
  if (receiptTemplate === 'simple') return <SimplePreview />
  return <CompactPreview />
}

export default InvoicePreview


