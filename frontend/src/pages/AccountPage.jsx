import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { exportAccount, deleteAccount } from '../api'
import { ErrorMessage, Field } from '../components/forms'

export default function AccountPage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const handleExport = async () => {
    setError(null)
    try {
      const data = await exportAccount()
      const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
      const link = document.createElement('a')
      link.href = url
      link.download = 'aqoj-mes-donnees.json'
      link.click()
      URL.revokeObjectURL(url)
    } catch (e) {
      setError(e.message)
    }
  }

  const handleDelete = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await deleteAccount(password)
      logout()
      navigate('/')
    } catch (err) {
      setError(err.message)
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div>
        <h1 className="text-4xl leading-tight sm:text-5xl">Mon compte</h1>
        <p className="mt-1 text-lg text-stone-600">Connecté·e avec <strong className="text-ink">{user?.email}</strong></p>
      </div>

      <ErrorMessage>{error}</ErrorMessage>

      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-export">
        <h2 id="titre-export" className="text-2xl">Récupérer mes données</h2>
        <p className="text-stone-600">
          Un fichier JSON avec votre compte, votre ludothèque, vos notes, vos parties et vos préférences.
        </p>
        <div>
          <button type="button" onClick={handleExport} className="btn btn-secondary">
            Télécharger mes données
          </button>
        </div>
      </section>

      <section className="panel flex flex-col gap-3 border-brand-red p-5 sm:p-6" aria-labelledby="titre-suppression">
        <h2 id="titre-suppression" className="text-2xl">Supprimer mon compte</h2>
        <p className="text-stone-600">
          Votre compte, votre ludothèque, vos parties et vos préférences sont effacés immédiatement et
          définitivement.
        </p>
        {confirming ? (
          <form onSubmit={handleDelete} className="flex flex-col gap-3">
            <Field
              label="Votre mot de passe, pour confirmer"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <div className="flex flex-wrap gap-3">
              <button type="submit" disabled={busy} className="btn bg-brand-red">
                Supprimer définitivement
              </button>
              <button type="button" onClick={() => { setConfirming(false); setPassword('') }} className="btn bg-paper">
                Annuler
              </button>
            </div>
          </form>
        ) : (
          <div>
            <button type="button" onClick={() => setConfirming(true)} className="btn bg-paper">
              Supprimer mon compte
            </button>
          </div>
        )}
      </section>
    </div>
  )
}
