import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { exportAccount, deleteAccount } from '../api'

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
    <div className="max-w-2xl mx-auto px-4 py-8 space-y-6">
      <h1 className="text-2xl font-black text-stone-900">Mon compte</h1>
      <p className="text-sm text-stone-600">Connecté·e avec {user?.email}</p>

      {error && <p role="alert" className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{error}</p>}

      <section className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-3">
        <h2 className="font-bold text-stone-900">Récupérer mes données</h2>
        <p className="text-sm text-stone-600">
          Un fichier JSON avec votre compte, votre ludothèque, vos notes, vos parties et vos préférences.
        </p>
        <button
          type="button"
          onClick={handleExport}
          className="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold"
        >
          Télécharger mes données
        </button>
      </section>

      <section className="bg-white rounded-2xl border border-red-200 shadow-sm p-6 space-y-3">
        <h2 className="font-bold text-red-800">Supprimer mon compte</h2>
        <p className="text-sm text-stone-600">
          Votre compte, votre ludothèque, vos parties et vos préférences sont effacés immédiatement et
          définitivement.
        </p>
        {confirming ? (
          <form onSubmit={handleDelete} className="space-y-3">
            <label className="block text-sm font-medium text-stone-700" htmlFor="delete-password">
              Votre mot de passe, pour confirmer
            </label>
            <input
              id="delete-password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-3 py-2 rounded-lg border border-stone-300 focus:outline-none focus:ring-2 focus:ring-red-400"
            />
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={busy}
                className="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white text-sm font-semibold"
              >
                Supprimer définitivement
              </button>
              <button
                type="button"
                onClick={() => { setConfirming(false); setPassword('') }}
                className="px-4 py-2 rounded-lg border border-stone-300 text-sm text-stone-700 hover:bg-stone-100"
              >
                Annuler
              </button>
            </div>
          </form>
        ) : (
          <button
            type="button"
            onClick={() => setConfirming(true)}
            className="px-4 py-2 rounded-lg border border-red-300 text-red-700 text-sm font-semibold hover:bg-red-50"
          >
            Supprimer mon compte
          </button>
        )}
      </section>
    </div>
  )
}
