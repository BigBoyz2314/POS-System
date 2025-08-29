import React, { useState, useEffect } from 'react'
import { useNavigate, Link, useLocation } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useHeader } from '../contexts/HeaderContext'

const Header: React.FC = () => {
  const [isDark, setIsDark] = useState(false)
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const { user, logout } = useAuth()
  const { showHeader, toggleHeader } = useHeader()
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    // Initialize theme
    const savedTheme = localStorage.getItem('theme')
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      setIsDark(true)
      document.documentElement.classList.add('dark')
      document.documentElement.classList.remove('theme-light')
    } else {
      setIsDark(false)
      document.documentElement.classList.remove('dark')
      document.documentElement.classList.add('theme-light')
    }
  }, [])

  const toggleTheme = () => {
    const newTheme = !isDark
    setIsDark(newTheme)
    
    if (newTheme) {
      document.documentElement.classList.add('dark')
      document.documentElement.classList.remove('theme-light')
      localStorage.setItem('theme', 'dark')
    } else {
      document.documentElement.classList.remove('dark')
      document.documentElement.classList.add('theme-light')
      localStorage.setItem('theme', 'light')
    }
  }

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const isActive = (path: string) => {
    return location.pathname === path
  }

  return (
    <div className="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <nav className="bg-white dark:bg-gray-800 shadow-lg">
        <div className="w-full px-3 sm:px-4">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <div className="flex-shrink-0 flex items-center min-w-0">
                <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 truncate">Acumen Retail</h1>
              </div>
              <div className="hidden sm:ml-4 sm:flex sm:space-x-3">
                <Link 
                  to="/dashboard" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/dashboard')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-tachometer-alt mr-2"></i>Dashboard
                </Link>
                <Link 
                  to="/products" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/products')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-box mr-2"></i>Products
                </Link>
                <Link 
                  to="/vendors" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/vendors')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-truck mr-2"></i>Vendors
                </Link>
                <Link 
                  to="/purchases" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/purchases')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-shopping-cart mr-2"></i>Purchases
                </Link>
                <Link 
                  to="/sales" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/sales')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-cash-register mr-2"></i>POS Sales
                </Link>
                <Link 
                  to="/reports" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/reports')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-chart-bar mr-2"></i>Reports
                </Link>
                <Link 
                  to="/returns" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/returns')
                      ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-undo mr-2"></i>Returns
                </Link>
              </div>
            </div>
            <div className="flex items-center">
              <button 
                id="mobileMenuToggle" 
                className="sm:hidden inline-flex items-center justify-center w-9 h-9 mr-2 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700" 
                aria-expanded={isMobileMenuOpen}
                aria-controls="mobileMenu"
                title="Menu"
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              >
                <i className="fas fa-bars"></i>
              </button>
              <div className="hidden sm:ml-6 sm:flex sm:items-center">
                <div className="ml-3 relative">
                  <div className="flex items-center space-x-4">
                    <button 
                      id="darkModeToggle" 
                      type="button" 
                      className="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500" 
                      aria-pressed={isDark}
                      title={isDark ? "Switch to light mode" : "Switch to dark mode"}
                      onClick={toggleTheme}
                    >
                      <i className={`fas ${isDark ? 'fa-sun' : 'fa-moon'}`}></i>
                    </button>
                    <span className="text-gray-700 dark:text-gray-300 text-sm">Welcome, {user?.username || 'User'}</span>
                    <button 
                      onClick={handleLogout}
                      className="text-gray-500 hover:text-gray-700 text-sm font-medium"
                    >
                      <i className="fas fa-sign-out-alt mr-1"></i>Logout
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div id="mobileMenu" className={`${isMobileMenuOpen ? 'block' : 'hidden'} sm:hidden max-h-[60vh] overflow-y-auto`}>
          <div className="pt-2 pb-3 space-y-1">
            <Link 
              to="/dashboard" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/dashboard') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-tachometer-alt mr-2"></i>Dashboard
            </Link>
            <Link 
              to="/products" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/products') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-box mr-2"></i>Products
            </Link>
            <Link 
              to="/vendors" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/vendors') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-truck mr-2"></i>Vendors
            </Link>
            <Link 
              to="/purchases" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/purchases') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-shopping-cart mr-2"></i>Purchases
            </Link>
            <Link 
              to="/sales" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/sales') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-cash-register mr-2"></i>POS Sales
            </Link>
            <Link 
              to="/reports" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/reports') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-chart-bar mr-2"></i>Reports
            </Link>
            <Link 
              to="/returns" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/returns') 
                  ? 'border-blue-500 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' 
                  : 'border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-undo mr-2"></i>Returns
            </Link>
          </div>
        </div>
      </nav>
    </div>
  )
}

export default Header
