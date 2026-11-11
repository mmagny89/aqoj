import { useState } from 'react'
import { Link } from 'react-router-dom'
import { forgotPassword } from '../api'
import { AuthCard, ErrorMessage, Field } from '../components/forms'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await forgotPassword(email.trim())
      setSent(true)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthCard
      title="Mot de passe oublié"
      footer={<Link to="/connexion" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Retour à la connexion</Link>}
    >
      {sent ? (
        <p role="status" className="rounded-xl border-[3px] border-ink bg-cream p-4 leading-relaxed">
          Si un compte existe avec cette adresse, un e-mail vient de lui être envoyé. Le lien est valable une heure.
          Pensez à regarder dans les courriers indésirables.
        </p>
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <p className="text-stone-700">Indiquez l'adresse de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
          <Field label="E-mail" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
          <ErrorMessage>{error}</ErrorMessage>
          <button type="submit" disabled={loading || !email.trim()} className="btn btn-primary w-full">
            {loading ? 'Envoi…' : 'Envoyer le lien'}
          </button>
        </form>
      )}
    </AuthCard>
  )
}
