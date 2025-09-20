import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

export interface PublicProduct {
  id: number
  name: string
  slug: string
  price: number
  web_price?: number | null
  display_price: number
  stock: number
  featured: number
  sort_order: number
  image_url?: string | null
}

export interface Category {
  id: number
  name: string
  slug: string
  description?: string
  sort_order: number
}


