import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/connexion" replace />
  }

  return children
}
