import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { verifyEmail } from '../api'
import { useAuth } from '../context/AuthContext'
import { AuthCard } from '../components/forms'

/** Page du lien reçu par e-mail : confirme l'adresse, puis ouvre la session. */
export default function VerifyEmailPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('jeton') ?? ''
  const { login } = useAuth()
  const navigate = useNavigate()
  const [state, setState] = useState(token ? 'loading' : 'missing')
  const [error, setError] = useState(null)
  // Le jeton ne sert qu'une fois : le double rendu du mode strict ne doit pas l'envoyer deux fois.
  const started = useRef(false)

  useEffect(() => {
    if (!token || started.current) return
    started.current = true
    verifyEmail(token)
      .then(data => {
        login(data.token, data.user)
        setState('done')
        navigate('/', { replace: true })
      })
      .catch(e => {
        setError(e.message)
        setState('error')
      })
  }, [token, login, navigate])

  const again = <Link to="/connexion" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-blue underline underline-offset-4">Aller à la connexion</Link>

  if (state === 'loading' || state === 'done') {
    return (
      <AuthCard title="Confirmation en cours">
        <p role="status" className="text-stone-700">Un instant, on confirme votre adresse…</p>
      </AuthCard>
    )
  }

  return (
    <AuthCard title={state === 'missing' ? 'Lien incomplet' : 'Lien non valable'} footer={again}>
      <p role={state === 'error' ? 'alert' : undefined}>
        {state === 'missing'
          ? "Ce lien ne contient pas de jeton. Ouvrez-le depuis l'e-mail reçu."
          : `${error} Sur la page de connexion, entrez votre mot de passe : on vous proposera d'envoyer un nouveau lien.`}
      </p>
    </AuthCard>
  )
}
