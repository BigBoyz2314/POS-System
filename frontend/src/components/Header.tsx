import React, { useState, useEffect } from 'react'
import { useNavigate, Link, useLocation } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useHeader } from '../contexts/HeaderContext'
import { useSettings } from '../contexts/SettingsContext'

const Header: React.FC = () => {
  const [isDark, setIsDark] = useState(false)
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const { user, logout } = useAuth()
  useHeader()
  const [isReportsMenuOpen, setIsReportsMenuOpen] = useState(false)
  const [isVendorsMenuOpen, setIsVendorsMenuOpen] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()
  const { business } = useSettings()

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
      <nav className="relative z-40 bg-white dark:bg-gray-800/60 shadow-lg backdrop-blur">
        <div className="w-full px-3 sm:px-4">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <Link to="/dashboard" className="flex-shrink-0 flex items-center min-w-0">
                <img src={business.logoUrl || '/logo-light.png'} alt="Logo" className="h-8 w-24 rounded mr-2" />
                {/* <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 truncate">{business.name || 'POS'}</h1> */}
              </Link>
              <div className="hidden sm:ml-4 sm:flex sm:space-x-3">
                <Link 
                  to="/dashboard" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/dashboard')
                      ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-tachometer-alt mr-2"></i>Dashboard
                </Link>
                <Link 
                  to="/products" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/products')
                      ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-box mr-2"></i>Products
                </Link>
                <div className="relative">
                  <button
                    onClick={() => setIsVendorsMenuOpen(v => !v)}
                    className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                      (isActive('/vendors') || isActive('/purchases'))
                        ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    }`}
                  >
                    <i className="fas fa-truck mr-2"></i>Vendors
                    <i className={`fas fa-chevron-down ml-2 text-xs transition-transform ${isVendorsMenuOpen ? 'rotate-180' : ''}`}></i>
                  </button>
                  {isVendorsMenuOpen && (
                    <div className="absolute z-[999] mt-2 w-44 rounded-md shadow-lg bg-white dark:bg-gray-800/60 backdrop-blur border border-gray-200 dark:border-gray-700">
                      <div className="py-1">
                        <Link 
                          to="/vendors"
                          onClick={() => setIsVendorsMenuOpen(false)}
                          className={`block px-4 py-2 text-sm ${isActive('/vendors') ? 'text-blue-700 dark:text-blue-500' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
                        >
                          <i className="fas fa-users mr-2"></i>Vendors
                        </Link>
                        <Link 
                          to="/purchases"
                          onClick={() => setIsVendorsMenuOpen(false)}
                          className={`block px-4 py-2 text-sm ${isActive('/purchases') ? 'text-blue-700 dark:text-blue-500' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
                        >
                          <i className="fas fa-shopping-cart mr-2"></i>Purchases
                        </Link>
                      </div>
                    </div>
                  )}
                </div>
                <Link 
                  to="/sales" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/sales')
                      ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-cash-register mr-2"></i>POS Sales
                </Link>
                <div className="relative">
                  <button
                    onClick={() => setIsReportsMenuOpen(v => !v)}
                    className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                      (isActive('/reports') || isActive('/returns'))
                        ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    }`}
                  >
                    <i className="fas fa-chart-bar mr-2"></i>Reports
                    <i className={`fas fa-chevron-down ml-2 text-xs transition-transform ${isReportsMenuOpen ? 'rotate-180' : ''}`}></i>
                  </button>
                  {isReportsMenuOpen && (
                    <div className="absolute z-[999] mt-2 w-44 rounded-md shadow-lg bg-white dark:bg-gray-800/60 backdrop-blur border border-gray-200 dark:border-gray-700">
                      <div className="py-1">
                        <Link 
                          to="/reports"
                          onClick={() => setIsReportsMenuOpen(false)}
                          className={`block px-4 py-2 text-sm ${isActive('/reports') ? 'text-blue-700 dark:text-blue-500' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
                        >
                          <i className="fas fa-chart-line mr-2"></i>Reports
                        </Link>
                        <Link 
                          to="/returns"
                          onClick={() => setIsReportsMenuOpen(false)}
                          className={`block px-4 py-2 text-sm ${isActive('/returns') ? 'text-blue-700 dark:text-blue-500' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'}`}
                        >
                          <i className="fas fa-undo mr-2"></i>Returns
                        </Link>
                      </div>
                    </div>
                  )}
                </div>
                <Link 
                  to="/settings" 
                  className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium ${
                    isActive('/settings')
                      ? '!text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <i className="fas fa-cog mr-2"></i>Settings
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
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80' 
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-tachometer-alt mr-2"></i>Dashboard
            </Link>
            <Link 
              to="/products" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/products') 
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80' 
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-box mr-2"></i>Products
            </Link>
            <details className="group">
              <summary className={`pl-3 pr-4 py-2 border-l-4 text-base font-medium list-none cursor-pointer ${
                (isActive('/vendors') || isActive('/purchases'))
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}>
                <i className="fas fa-truck mr-2"></i>Vendors
                <i className="fas fa-chevron-down ml-2 text-xs transition-transform"></i>
              </summary>
              <div className="ml-6 mt-1 space-y-1">
                <Link 
                  to="/vendors" 
                  className={`block pl-3 pr-4 py-2 text-base font-medium ${
                    isActive('/vendors') 
                      ? '!text-blue-700 dark:text-blue-500 bg-blue-100/40 dark:bg-blue-900/10' 
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white'
                  }`}
                >
                  <i className="fas fa-users mr-2"></i>Vendors
                </Link>
                <Link 
                  to="/purchases" 
                  className={`block pl-3 pr-4 py-2 text-base font-medium ${
                    isActive('/purchases') 
                      ? '!text-blue-700 dark:text-blue-500 bg-blue-100/40 dark:bg-blue-900/10' 
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white'
                  }`}
                >
                  <i className="fas fa-shopping-cart mr-2"></i>Purchases
                </Link>
              </div>
            </details>
            <Link 
              to="/sales" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/sales') 
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80' 
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-cash-register mr-2"></i>POS Sales
            </Link>
            <details className="group">
              <summary className={`pl-3 pr-4 py-2 border-l-4 text-base font-medium list-none cursor-pointer ${
                (isActive('/reports') || isActive('/returns'))
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80'
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}>
                <i className="fas fa-chart-bar mr-2"></i>Reports
                <i className="fas fa-chevron-down ml-2 text-xs transition-transform"></i>
              </summary>
              <div className="ml-6 mt-1 space-y-1">
                <Link 
                  to="/reports" 
                  className={`block pl-3 pr-4 py-2 text-base font-medium ${
                    isActive('/reports') 
                      ? '!text-blue-700 dark:text-blue-500 bg-blue-100/40 dark:bg-blue-900/10' 
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white'
                  }`}
                >
                  <i className="fas fa-chart-line mr-2"></i>Reports
                </Link>
                <Link 
                  to="/returns" 
                  className={`block pl-3 pr-4 py-2 text-base font-medium ${
                    isActive('/returns') 
                      ? '!text-blue-700 dark:text-blue-500 bg-blue-100/40 dark:bg-blue-900/10' 
                      : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-white'
                  }`}
                >
                  <i className="fas fa-undo mr-2"></i>Returns
                </Link>
              </div>
            </details>
            <Link 
              to="/settings" 
              className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                isActive('/settings') 
                  ? 'border-blue-500 !text-blue-700 dark:!text-blue-100 !bg-blue-100/80 dark:!bg-blue-900/80' 
                  : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white'
              }`}
            >
              <i className="fas fa-cog mr-2"></i>Settings
            </Link>
          </div>
        </div>
      </nav>
    </div>
  )
}

export default Header
