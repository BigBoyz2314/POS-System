import axios from 'axios'

// Use dev proxy in development and relative 'api' path in production
const baseURL = import.meta.env.PROD ? 'api' : '/api'

const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Important for session cookies
})

// Add request interceptor to include CSRF token if needed
api.interceptors.request.use((config) => {
  // You can add authentication headers here if needed
  return config
})

// Add response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API Error:', error)
    return Promise.reject(error)
  }
)

interface SaleData {
  items: Array<{
    product_id: number
    quantity: number
    price: number
    tax_rate?: number
  }>
  total: number
  customer_name?: string
  payment_method?: string
}

export const apiService = {
  // Products
  getProducts: () => api.get('products.php'),
  
  // Sales
  createSale: (saleData: SaleData) => api.post('sales.php', saleData),
  createSaleForm: (formData: FormData) => api.post('sales.php', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  getSales: () => api.get('sales.php'),
  
  // Returns
  getSaleDetails: (saleId: number) => api.get(`sale_details.php`, { params: { sale_id: saleId } }),
  processReturn: (payload: {
    sale_id: number
    items: Array<{ sale_item_id: number | null; product_id: number; quantity: number; reason: string }>
    refund_method: 'cash' | 'card' | 'mixed'
    refund_cash: number
    refund_card: number
  }) => api.post('process_return.php', payload),

  // Authentication
  login: (credentials: { username: string; password: string }) => 
    api.post('login.php', credentials),
  logout: () => api.post('logout.php'),
  checkAuth: () => api.get('login.php'),
  
  // Generic API call
  get: (url: string) => api.get(url),
  post: (url: string, data: Record<string, unknown>) => api.post(url, data),
  put: (url: string, data: Record<string, unknown>) => api.put(url, data),
  delete: (url: string) => api.delete(url),
}

export default api
