import React from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { HeaderProvider, useHeader } from './contexts/HeaderContext'
import { useAuth } from './hooks/useAuth'
import Header from './components/Header'
import Sales from './pages/Sales'
import Dashboard from './pages/Dashboard'
import Products from './pages/Products'
import Vendors from './pages/Vendors'
import Purchases from './pages/Purchases'
import Reports from './pages/Reports'
import Returns from './pages/Returns'
import Login from './pages/Login'
import './App.css'

// Protected Route component
const ProtectedRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, loading } = useAuth()

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-400">Loading...</p>
        </div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}

// Public Route component (redirects to dashboard if already authenticated)
const PublicRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, loading } = useAuth()

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-400">Loading...</p>
        </div>
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

const SalesWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Sales />
    </>
  )
}

const DashboardWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Dashboard />
    </>
  )
}

const ProductsWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Products />
    </>
  )
}

const VendorsWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Vendors />
    </>
  )
}

const PurchasesWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Purchases />
    </>
  )
}

const ReportsWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Reports />
    </>
  )
}

const ReturnsWithHeader: React.FC = () => {
  const { showHeader } = useHeader()
  
  return (
    <>
      {showHeader && <Header />}
      <Returns />
    </>
  )
}

const AppRoutes: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <Routes>
        <Route path="/login" element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        } />
        <Route path="/dashboard" element={
          <ProtectedRoute>
            <DashboardWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/products" element={
          <ProtectedRoute>
            <ProductsWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/vendors" element={
          <ProtectedRoute>
            <VendorsWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/purchases" element={
          <ProtectedRoute>
            <PurchasesWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/reports" element={
          <ProtectedRoute>
            <ReportsWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/returns" element={
          <ProtectedRoute>
            <ReturnsWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/sales" element={
          <ProtectedRoute>
            <SalesWithHeader />
          </ProtectedRoute>
        } />
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </div>
  )
}

const App: React.FC = () => {
  return (
    <AuthProvider>
      <HeaderProvider>
        <Router>
          <AppRoutes />
        </Router>
      </HeaderProvider>
    </AuthProvider>
  )
}

export default App
