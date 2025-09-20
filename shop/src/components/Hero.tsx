import React from 'react'
import { Link } from 'react-router-dom'
import axios from 'axios'

export default function Hero(): JSX.Element {
  const [content, setContent] = React.useState<{hero_title?: string, hero_subtitle?: string, promo_text?: string}>({})
  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await axios.get('/api/shop_content.php')
        if (!mounted) return
        setContent(res.data?.content || {})
      } catch {}
    })()
    return () => { mounted = false }
  }, [])

  return (
    <div className="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50">
      <div className="absolute inset-0 opacity-10" style={{backgroundImage:'radial-gradient(circle at 20% 20%, #60a5fa 0, transparent 50%), radial-gradient(circle at 80% 0%, #a78bfa 0, transparent 40%)'}} />
      <div className="relative px-6 py-12 md:px-10 md:py-16 flex flex-col md:flex-row md:items-center gap-6">
        <div className="flex-1">
          <h1 className="text-3xl md:text-4xl font-bold tracking-tight text-gray-900">{content.hero_title || 'Discover products you’ll love'}</h1>
          <p className="mt-3 text-gray-600 max-w-xl">{content.hero_subtitle || 'Fresh arrivals, curated picks, and everyday essentials. Order now and get fast delivery.'}</p>
          <div className="mt-6 flex items-center gap-3">
            <Link to="/" className="px-4 py-2 rounded-md bg-blue-600 text-white">Shop Now</Link>
            <a href="#featured" className="px-4 py-2 rounded-md bg-white text-blue-700 border border-blue-200">Browse Featured</a>
          </div>
        </div>
        <div className="flex-1">
          <div className="aspect-[4/3] rounded-xl bg-white/70 border shadow-sm flex items-center justify-center">
            <span className="text-gray-400 text-sm">Banner image</span>
          </div>
        </div>
      </div>
    </div>
  )
}


