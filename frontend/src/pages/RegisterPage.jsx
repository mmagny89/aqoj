import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getRegistrationConfig, register as apiRegister } from '../api'
import { AuthCard, ErrorMessage, Field, PasswordField } from '../components/forms'
import ResendVerification from '../components/ResendVerification'

export default function RegisterPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [code, setCode] = useState('')
  const [sent, setSent] = useState(false)
  const [requiresCode, setRequiresCode] = useState(false)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  // Le formulaire réclame le code seulement si l'inscription l'exige (staging, production).
  useEffect(() => {
    getRegistrationConfig().then(config => setRequiresCode(Boolean(config?.requiresCode))).catch(() => {})
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError(null)

    if (password !== confirm) {
      setError('Les mots de passe ne correspondent pas.')
      return
    }

    setLoading(true)
    try {
      // Pas de session : l'adresse doit d'abord être confirmée par le lien envoyé.
      await apiRegister(email, password, code.trim())
      setSent(true)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  if (sent) {
    return (
      <AuthCard
        title="Vérifiez vos e-mails"
        footer={<Link to="/connexion" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Retour à la connexion</Link>}
      >
        <p role="status" className="leading-relaxed">
          Compte créé. Un e-mail de confirmation vient de partir pour <strong className="break-all">{email}</strong> :
          ouvrez le lien qu'il contient pour activer votre compte. Il est valable sept jours.
        </p>
        <ResendVerification email={email} />
      </AuthCard>
    )
  }

  return (
    <AuthCard
      title="Créer un compte"
      footer={<>Déjà un compte ? <Link to="/connexion" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Se connecter</Link></>}
    >
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Field label="E-mail" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
        <Field
          label="Code d'invitation"
          hint={requiresCode ? '(donné par l\'association)' : '(facultatif)'}
          type="text"
          autoComplete="off"
          autoCapitalize="characters"
          required={requiresCode}
          value={code}
          onChange={(e) => setCode(e.target.value)}
        />
        <PasswordField label="Mot de passe" hint="(8 caractères minimum)" autoComplete="new-password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} />
        <PasswordField label="Confirmer le mot de passe" autoComplete="new-password" required value={confirm} onChange={(e) => setConfirm(e.target.value)} />
        <ErrorMessage>{error}</ErrorMessage>
        <button type="submit" disabled={loading} className="btn btn-primary w-full">
          {loading ? 'Création…' : 'Créer mon compte'}
        </button>
      </form>
    </AuthCard>
  )
}
