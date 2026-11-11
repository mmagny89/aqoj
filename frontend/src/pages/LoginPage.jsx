import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { login as apiLogin } from '../api'
import { useAuth } from '../context/AuthContext'
import { AuthCard, ErrorMessage, Field, PasswordField } from '../components/forms'
import ResendVerification from '../components/ResendVerification'

export default function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [unverified, setUnverified] = useState(false)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError(null)
    setUnverified(false)
    setLoading(true)
    try {
      const data = await apiLogin(email, password)
      login(data.token, data.user)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err.message)
      setUnverified(err.code === 'email_not_verified')
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthCard
      title="Connexion"
      footer={<>Pas encore de compte ? <Link to="/inscription" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Créer un compte</Link></>}
    >
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Field label="E-mail" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
        <PasswordField label="Mot de passe" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
        <p className="-mt-2 text-right">
          <Link to="/mot-de-passe-oublie" className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Mot de passe oublié ?</Link>
        </p>
        <ErrorMessage>{error}</ErrorMessage>
        {unverified && <ResendVerification email={email.trim()} />}
        <button type="submit" disabled={loading} className="btn btn-primary w-full">
          {loading ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>
    </AuthCard>
  )
}
