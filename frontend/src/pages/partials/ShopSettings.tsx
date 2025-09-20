import React from 'react'
import axios from 'axios'

export default function ShopSettings(): React.JSX.Element {
  const [heroTitle, setHeroTitle] = React.useState('')
  const [heroSubtitle, setHeroSubtitle] = React.useState('')
  const [promo, setPromo] = React.useState('')
  const [featuredIds, setFeaturedIds] = React.useState('')
  const [saving, setSaving] = React.useState(false)
  const [msg, setMsg] = React.useState<string | null>(null)

  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await axios.get('/api/shop_content.php')
        const c = res.data?.content || {}
        if (!mounted) return
        setHeroTitle(c.hero_title || '')
        setHeroSubtitle(c.hero_subtitle || '')
        setPromo(c.promo_text || '')
        setFeaturedIds(c.featured_product_ids || '')
      } catch {}
    })()
    return () => { mounted = false }
  }, [])

  const save = async () => {
    setSaving(true)
    setMsg(null)
    try {
      await axios.post('/api/shop_content.php', {
        hero_title: heroTitle,
        hero_subtitle: heroSubtitle,
        promo_text: promo,
        featured_product_ids: featuredIds,
      })
      setMsg('Shop content saved')
    } catch (e: any) {
      setMsg(e?.response?.data?.error || 'Failed to save')
    } finally {
      setSaving(false)
      setTimeout(() => setMsg(null), 2000)
    }
  }

  return (
    <div className="space-y-4">
      {msg && <div className="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{msg}</div>}
      <div>
        <label className="block text-sm font-medium mb-1">Hero Title</label>
        <input value={heroTitle} onChange={e=>setHeroTitle(e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Hero Subtitle</label>
        <input value={heroSubtitle} onChange={e=>setHeroSubtitle(e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Promo Text (top bar)</label>
        <input value={promo} onChange={e=>setPromo(e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Featured Product IDs (comma-separated)</label>
        <input value={featuredIds} onChange={e=>setFeaturedIds(e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700" />
      </div>
      <div className="pt-2">
        <button onClick={save} disabled={saving} className="px-3 py-2 rounded bg-blue-600 text-white text-sm disabled:opacity-60">{saving ? 'Saving...' : 'Save'}</button>
      </div>
    </div>
  )
}


