import React, { createContext, useState, useContext, useEffect } from 'react'

interface HeaderContextType {
  showHeader: boolean
  setShowHeader: (show: boolean) => void
  toggleHeader: () => void
}

const HeaderContext = createContext<HeaderContextType | undefined>(undefined)

export const useHeader = () => {
  const context = useContext(HeaderContext)
  if (context === undefined) {
    throw new Error('useHeader must be used within a HeaderProvider')
  }
  return context
}

interface HeaderProviderProps {
  children: React.ReactNode
}

// Initialize header state from localStorage
const getInitialHeaderState = (): boolean => {
  try {
    const saved = localStorage.getItem('header_visible')
    if (saved !== null) {
      return JSON.parse(saved)
    }
  } catch (error) {
    console.error('Error loading header state from localStorage:', error)
  }
  return true // Default to showing header if no saved state
}

export const HeaderProvider: React.FC<HeaderProviderProps> = ({ children }) => {
  const [showHeader, setShowHeader] = useState(getInitialHeaderState)

  // Save header state to localStorage whenever it changes
  useEffect(() => {
    try {
      localStorage.setItem('header_visible', JSON.stringify(showHeader))
    } catch (error) {
      console.error('Error saving header state to localStorage:', error)
    }
  }, [showHeader])

  const toggleHeader = () => {
    setShowHeader(prev => !prev)
  }

  return (
    <HeaderContext.Provider value={{ showHeader, setShowHeader, toggleHeader }}>
      {children}
    </HeaderContext.Provider>
  )
}
