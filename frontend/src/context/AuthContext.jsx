import { createContext, useContext, useState, useEffect } from 'react'
import { getMe } from '../api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('aqoj_token')
    if (!token) {
      setLoading(false)
      return
    }
    getMe()
      .then(u => setUser(u))
      .catch(() => localStorage.removeItem('aqoj_token'))
      .finally(() => setLoading(false))
  }, [])

  const login = (token, userData) => {
    localStorage.setItem('aqoj_token', token)
    setUser(userData)
  }

  const logout = () => {
    localStorage.removeItem('aqoj_token')
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
