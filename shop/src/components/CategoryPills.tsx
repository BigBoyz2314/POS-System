import React from 'react'
import { Link } from 'react-router-dom'
import { api, Category } from '../lib/api'

export default function CategoryPills(): JSX.Element {
  const [cats, setCats] = React.useState<Category[]>([])
  React.useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        const res = await api.get('/categories.php')
        if (!mounted) return
        setCats(res.data.categories || [])
      } catch {}
    })()
    return () => { mounted = false }
  }, [])

  if (!cats.length) return <></>

  return (
    <div className="flex flex-wrap gap-2 mt-4">
      {cats.map(c => (
        <Link key={c.id} to={`/category/${c.slug}`} className="px-3 py-1.5 rounded-full text-sm bg-white border hover:bg-gray-50">
          {c.name}
        </Link>
      ))}
    </div>
  )
}


