import React, { useRef, useState } from 'react'
import axios from 'axios'
import { useSettings } from '../contexts/SettingsContext'
import InvoicePreview from '../components/InvoicePreview'
import ShopSettings from './partials/ShopSettings'

const Settings: React.FC = () => {
  const { receiptTemplate, setReceiptTemplate, business, updateBusiness } = useSettings()
  const [activeSection, setActiveSection] = useState<'receipt' | 'business'>('receipt')
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const saveAll = async () => {
    setSaving(true)
    setMessage(null)
    try {
      await axios.post('/api/settings.php', {
        business_name: business.name,
        business_address: business.address,
        business_phone: business.phone,
        logo_url: business.logoUrl,
        receipt_template: receiptTemplate,
      })
      setMessage('Settings saved')
    } catch (e: any) {
      setMessage(e?.response?.data?.error || 'Failed to save')
    } finally {
      setSaving(false)
      setTimeout(() => setMessage(null), 2000)
    }
  }

  const onPickLogo = () => fileRef.current?.click()
  const onUploadLogo: React.ChangeEventHandler<HTMLInputElement> = async (e) => {
    const f = e.target.files?.[0]
    if (!f) return
    const form = new FormData()
    form.append('file', f)
    setSaving(true)
    setMessage(null)
    try {
      const res = await axios.post('/api/upload_logo.php', form, { headers: { 'Content-Type': 'multipart/form-data' } })
      if (res.data?.url) {
        updateBusiness({ logoUrl: res.data.url })
        await axios.post('/api/settings.php', {
          business_name: business.name,
          business_address: business.address,
          business_phone: business.phone,
          logo_url: res.data.url,
          receipt_template: receiptTemplate,
        })
        setMessage('Logo uploaded')
      }
    } catch (e: any) {
      setMessage(e?.response?.data?.error || 'Upload failed')
    } finally {
      setSaving(false)
      if (fileRef.current) fileRef.current.value = ''
      setTimeout(() => setMessage(null), 2000)
    }
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <div className="container mx-auto px-4 py-8 relative">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">Settings</h1>
          <div className="flex items-center gap-2">
            <button onClick={onPickLogo} className="px-3 py-2 rounded bg-white dark:bg-gray-800 border text-sm">Upload Logo</button>
            <button onClick={saveAll} disabled={saving} className="px-3 py-2 rounded bg-blue-600 text-white text-sm disabled:opacity-60">{saving ? 'Saving...' : 'Save'}</button>
          </div>
        </div>
        {message && <div className="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{message}</div>}
        <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={onUploadLogo} />

        <div className="flex">
          {/* Floating sidebar */}
          <div className="w-56 mr-6 sticky top-4 self-start">
            <div className="bg-white dark:bg-gray-800/60 backdrop-blur rounded-lg shadow p-3 space-y-1">
              <button onClick={() => setActiveSection('receipt')} className={`w-full text-left px-3 py-2 rounded-md border-l-4 font-medium ${activeSection === 'receipt' ? 'border-blue-500 !bg-blue-100/80 !text-blue-500 dark:border-blue-600 dark:bg-blue-900/20 dark:!text-blue-600' : 'border-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}>Receipt Template</button>
              <button onClick={() => setActiveSection('business')} className={`w-full text-left px-3 py-2 rounded-md border-l-4 font-medium ${activeSection === 'business' ? 'border-blue-500 !bg-blue-100/80 !text-blue-500 dark:border-blue-600 dark:bg-blue-900/20 dark:!text-blue-600' : 'border-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'}`}>Business Info</button>
              <a href="#shop" className="block w-full text-left px-3 py-2 rounded-md border-l-4 font-medium border-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Shop</a>
            </div>
          </div>

          {/* Content area */}
          <div className="flex-1">
            {activeSection === 'receipt' && (
              <>
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-lg font-semibold mb-4">Receipt Template</h2>
                  <div className="space-y-2">
                    <label className="flex items-center space-x-2">
                      <input type="radio" name="template" className="accent-blue-600" checked={receiptTemplate === 'compact'} onChange={() => setReceiptTemplate('compact')} />
                      <span>Compact (80mm) - current format</span>
                    </label>
                    <label className="flex items-center space-x-2">
                      <input type="radio" name="template" className="accent-blue-600" checked={receiptTemplate === 'detailed'} onChange={() => setReceiptTemplate('detailed')} />
                      <span>Detailed (A4-like)</span>
                    </label>
                    <label className="flex items-center space-x-2">
                      <input type="radio" name="template" className="accent-blue-600" checked={receiptTemplate === 'simple'} onChange={() => setReceiptTemplate('simple')} />
                      <span>Simple (minimal)</span>
                    </label>
                  </div>
                </div>
                <div className="mt-6">
                  <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 className="text-md font-semibold mb-4">Preview</h3>
                    <div className="flex justify-center"><InvoicePreview /></div>
                  </div>
                </div>
              </>
            )}

            {activeSection === 'business' && (
              <>
              <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold mb-4">Business Information</h2>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Name</label>
                    <input value={business.name} onChange={e => updateBusiness({ name: e.target.value })} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Address</label>
                    <input value={business.address} onChange={e => updateBusiness({ address: e.target.value })} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Phone</label>
                    <input value={business.phone} onChange={e => updateBusiness({ phone: e.target.value })} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Logo URL</label>
                    <input value={business.logoUrl} onChange={e => updateBusiness({ logoUrl: e.target.value })} placeholder="https://... or /uploads/logo.png" className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
                  </div>
                  <div className="pt-2">
                    <button onClick={saveAll} disabled={saving} className="px-3 py-2 rounded bg-blue-600 text-white text-sm disabled:opacity-60">{saving ? 'Saving...' : 'Save'}</button>
                    <button onClick={onPickLogo} className="ml-2 px-3 py-2 rounded bg-white dark:bg-gray-800 border text-sm">Upload Logo</button>
                  </div>
                </div>
              </div>
              <div id="shop" className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
                <h2 className="text-lg font-semibold mb-4">Shop Content</h2>
                <ShopSettings />
              </div>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

export default Settings


