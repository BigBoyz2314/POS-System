import React, { createContext, useCallback, useContext, useMemo, useRef, useState, useEffect } from 'react'

type ConfirmOptions = {
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  danger?: boolean
}

type ConfirmContextValue = {
  confirm: (options: ConfirmOptions) => Promise<boolean>
}

const ConfirmContext = createContext<ConfirmContextValue | undefined>(undefined)

export const useConfirm = (): ConfirmContextValue => {
  const ctx = useContext(ConfirmContext)
  if (!ctx) throw new Error('useConfirm must be used within ConfirmProvider')
  return ctx
}

export const ConfirmProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [isOpen, setIsOpen] = useState(false)
  const [options, setOptions] = useState<ConfirmOptions>({})
  const resolverRef = useRef<(value: boolean) => void>(() => {})
  const confirmBtnRef = useRef<HTMLButtonElement>(null)

  const confirm = useCallback((opts: ConfirmOptions) => {
    setOptions(opts)
    setIsOpen(true)
    return new Promise<boolean>((resolve) => {
      resolverRef.current = resolve
    })
  }, [])

  const handleClose = useCallback((value: boolean) => {
    setIsOpen(false)
    if (resolverRef.current) resolverRef.current(value)
  }, [])

  useEffect(() => {
    if (!isOpen) return
    // Focus the primary (blue) button
    confirmBtnRef.current?.focus()
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        handleClose(true)
      }
      if (e.key === 'Escape') {
        e.preventDefault()
        handleClose(false)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [isOpen, handleClose])

  const value = useMemo(() => ({ confirm }), [confirm])

  return (
    <ConfirmContext.Provider value={value}>
      {children}
      {isOpen && (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center">
          <div className="absolute inset-0 bg-black/40" onClick={() => handleClose(false)}></div>
          <div className="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-sm mx-4 p-5">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
              {options.title || 'Please confirm'}
            </h3>
            {options.message && (
              <p className="text-sm text-gray-700 dark:text-gray-300 mb-4">{options.message}</p>
            )}
            <div className="flex justify-end gap-2">
              <button
                className="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600"
                onClick={() => handleClose(false)}
              >
                {options.cancelText || 'Cancel'}
              </button>
              <button
                ref={confirmBtnRef}
                className={`${options.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'} px-3 py-2 rounded text-white`}
                onClick={() => handleClose(true)}
              >
                {options.confirmText || 'Confirm'}
              </button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  )
}


