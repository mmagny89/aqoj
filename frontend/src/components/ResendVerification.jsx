import { useState } from 'react'
import { resendVerification } from '../api'

/** Renvoie le lien de confirmation de l'adresse ; la réponse du serveur est la même que le compte existe ou non. */
export default function ResendVerification({ email }) {
  const [state, setState] = useState('idle')
  const [error, setError] = useState(null)

  const resend = async () => {
    setState('sending')
    setError(null)
    try {
      await resendVerification(email)
      setState('sent')
    } catch (e) {
      setError(e.message)
      setState('idle')
    }
  }

  if (state === 'sent') {
    return <p role="status" className="font-bold text-brand-green">Un nouvel e-mail vient de partir. Regardez aussi dans les courriers indésirables.</p>
  }

  return (
    <div className="flex flex-col items-start gap-2">
      <button type="button" onClick={resend} disabled={state === 'sending' || !email} className="btn btn-secondary">
        {state === 'sending' ? 'Envoi…' : "Renvoyer l'e-mail de confirmation"}
      </button>
      {error && <p role="alert" className="font-bold">{error}</p>}
    </div>
  )
}
