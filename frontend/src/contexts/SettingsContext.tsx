import React, { createContext, useContext, useEffect, useMemo, useState } from 'react'
import axios from 'axios'

type ReceiptTemplate = 'compact' | 'detailed' | 'simple'

interface BusinessInfo {
  name: string
  address: string
  phone: string
  logoUrl: string
}

interface SettingsState {
  receiptTemplate: ReceiptTemplate
  business: BusinessInfo
  setReceiptTemplate: (t: ReceiptTemplate) => void
  updateBusiness: (partial: Partial<BusinessInfo>) => void
}

const defaultBusiness: BusinessInfo = {
  name: 'Your Business Name',
  address: '123 Street, City',
  phone: '+92 300 0000000',
  logoUrl: '/logo-light.png'
}

const SettingsContext = createContext<SettingsState | undefined>(undefined)

export const SettingsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [receiptTemplate, setReceiptTemplateState] = useState<ReceiptTemplate>('compact')
  const [business, setBusiness] = useState<BusinessInfo>(defaultBusiness)

  useEffect(() => {
    ;(async () => {
      try {
        const res = await axios.get('/api/settings.php')
        const s = res.data?.settings
        if (s) {
          if (s.receipt_template) setReceiptTemplateState(s.receipt_template)
          setBusiness({
            name: s.business_name || defaultBusiness.name,
            address: s.business_address || defaultBusiness.address,
            phone: s.business_phone || defaultBusiness.phone,
            logoUrl: s.logo_url || defaultBusiness.logoUrl,
          })
        }
      } catch {
        try {
          const raw = localStorage.getItem('pos_settings')
          if (raw) {
            const parsed = JSON.parse(raw)
            if (parsed.receiptTemplate) setReceiptTemplateState(parsed.receiptTemplate)
            if (parsed.business) setBusiness({ ...defaultBusiness, ...parsed.business })
          }
        } catch {}
      }
    })()
  }, [])

  useEffect(() => {
    ;(async () => {
      try {
        await axios.post('/api/settings.php', {
          business_name: business.name,
          business_address: business.address,
          business_phone: business.phone,
          logo_url: business.logoUrl,
          receipt_template: receiptTemplate,
        })
      } catch {}
      try {
        localStorage.setItem('pos_settings', JSON.stringify({ receiptTemplate, business }))
      } catch {}
    })()
  }, [receiptTemplate, business])

  const value = useMemo<SettingsState>(() => ({
    receiptTemplate,
    business,
    setReceiptTemplate: (t) => setReceiptTemplateState(t),
    updateBusiness: (partial) => setBusiness(prev => ({ ...prev, ...partial }))
  }), [receiptTemplate, business])

  return (
    <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>
  )
}

export const useSettings = (): SettingsState => {
  const ctx = useContext(SettingsContext)
  if (!ctx) throw new Error('useSettings must be used within SettingsProvider')
  return ctx
}


