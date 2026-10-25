import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { register as apiRegister } from '../api'
import { useAuth } from '../context/AuthContext'
import { AuthCard, ErrorMessage, Field } from '../components/forms'

export default function RegisterPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError(null)

    if (password !== confirm) {
      setError('Les mots de passe ne correspondent pas.')
      return
    }

    setLoading(true)
    try {
      const data = await apiRegister(email, password)
      login(data.token, data.user)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthCard
      title="Créer un compte"
      footer={<>Déjà un compte ? <Link to="/connexion" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Se connecter</Link></>}
    >
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Field label="E-mail" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
        <Field label="Mot de passe" hint="(8 caractères minimum)" type="password" autoComplete="new-password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} />
        <Field label="Confirmer le mot de passe" type="password" autoComplete="new-password" required value={confirm} onChange={(e) => setConfirm(e.target.value)} />
        <ErrorMessage>{error}</ErrorMessage>
        <button type="submit" disabled={loading} className="btn btn-primary w-full">
          {loading ? 'Création…' : 'Créer mon compte'}
        </button>
      </form>
    </AuthCard>
  )
}
