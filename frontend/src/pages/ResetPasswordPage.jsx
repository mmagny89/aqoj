import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { resetPassword } from '../api'
import { AuthCard, ErrorMessage, PasswordField } from '../components/forms'

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('jeton') ?? ''
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [done, setDone] = useState(false)
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
      await resetPassword(token, password)
      setDone(true)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const retry = <Link to="/mot-de-passe-oublie" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Demander un nouveau lien</Link>

  if (!token) {
    return (
      <AuthCard title="Lien incomplet" footer={retry}>
        <p>Ce lien ne contient pas de jeton. Ouvrez-le depuis l'e-mail reçu, ou demandez-en un nouveau.</p>
      </AuthCard>
    )
  }

  if (done) {
    return (
      <AuthCard title="Mot de passe modifié">
        <p role="status">Votre mot de passe est changé. Vous pouvez maintenant vous connecter.</p>
        <Link to="/connexion" className="btn btn-primary w-full">Se connecter</Link>
      </AuthCard>
    )
  }

  return (
    <AuthCard title="Nouveau mot de passe" footer={retry}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <PasswordField label="Nouveau mot de passe" hint="(8 caractères minimum)" autoComplete="new-password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} />
        <PasswordField label="Confirmer le mot de passe" autoComplete="new-password" required value={confirm} onChange={(e) => setConfirm(e.target.value)} />
        <ErrorMessage>{error}</ErrorMessage>
        <button type="submit" disabled={loading} className="btn btn-primary w-full">
          {loading ? 'Enregistrement…' : 'Changer mon mot de passe'}
        </button>
      </form>
    </AuthCard>
  )
}
