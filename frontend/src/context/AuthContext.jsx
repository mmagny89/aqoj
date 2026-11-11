import { createContext, useCallback, useContext, useState, useEffect } from 'react'
import { getMe, refreshSession } from '../api'
import { needsRenewal } from '../session'

const RENEW_CHECK_MS = 5 * 60 * 1000

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

  // Renouvellement de la session : tant que la personne est là (onglet ouvert, au retour sur l'onglet), on
  // prolonge le jeton avant son expiration. Une panne réseau se réessaie au passage suivant ; un 401 (session
  // plafonnée à 30 jours, mot de passe changé ailleurs) renvoie à la connexion par `request()`.
  const renew = useCallback(async () => {
    const token = localStorage.getItem('aqoj_token')
    if (!token || !needsRenewal(token)) return
    try {
      const data = await refreshSession()
      if (data?.token) {
        localStorage.setItem('aqoj_token', data.token)
        setUser(data.user)
      }
    } catch {
      /* réessayé au prochain passage */
    }
  }, [])

  useEffect(() => {
    if (!user) return undefined
    const timer = setInterval(renew, RENEW_CHECK_MS)
    const onVisible = () => { if (document.visibilityState === 'visible') renew() }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [user?.id, renew]) // eslint-disable-line react-hooks/exhaustive-deps -- relancé à la connexion d'un compte, pas à chaque mise à jour du profil

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
