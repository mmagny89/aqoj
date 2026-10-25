import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { importBgg, getMe } from '../api'
import { ErrorMessage, Field } from '../components/forms'
import { useAuth } from '../context/AuthContext'

export default function ImportBggPage() {
  const { user, login } = useAuth()
  const [username, setUsername] = useState(user?.bggUsername ?? '')
  const [status, setStatus] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (user?.bggUsername && !username) {
      setUsername(user.bggUsername)
    }
  }, [user, username])

  const handleImport = async (e) => {
    e.preventDefault()
    if (!username.trim()) return
    setLoading(true)
    setError(null)
    setStatus(null)
    try {
      const data = await importBgg(username.trim())
      setStatus(data)
      // Rafraîchir le profil pour mettre à jour bggUsername dans le contexte
      const updatedUser = await getMe()
      const token = localStorage.getItem('aqoj_token')
      if (token) login(token, updatedUser)
    } catch (err) {
      setError(err.message || "Erreur lors de l'import")
    } finally {
      setLoading(false)
    }
  }

  const isExistingUsername = user?.bggUsername && user.bggUsername === username.trim()

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div>
        <h1 className="text-4xl leading-tight sm:text-5xl">Importer depuis BGG</h1>
        <p className="mt-1 text-lg text-stone-600">
          Reprenez votre collection BoardGameGeek pour la retrouver dans votre ludothèque.
        </p>
      </div>

      {user?.bggUsername && (
        <p className="panel flex items-center gap-3 bg-amber-100 p-4">
          <span className="badge bg-brand-yellow">Compte BGG lié</span>
          <strong>{user.bggUsername}</strong>
        </p>
      )}

      <form onSubmit={handleImport} className="panel flex flex-col gap-4 p-5 sm:p-6">
        <Field
          label="Pseudo BoardGameGeek"
          type="text"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          placeholder="Votre pseudo sur BGG"
          autoComplete="off"
        />

        <button type="submit" disabled={loading || !username.trim()} className="btn btn-primary w-full">
          {loading
            ? 'Import en cours… (1 à 2 minutes)'
            : isExistingUsername ? 'Synchroniser ma collection' : 'Importer ma collection'}
        </button>
        {loading && <p role="status" className="text-center text-stone-600">Patientez, on parle à BoardGameGeek.</p>}
      </form>

      <ErrorMessage>{error}</ErrorMessage>

      {status && (
        <section className="panel flex flex-col gap-4 bg-cream p-5 sm:p-6" aria-labelledby="titre-bilan" role="status">
          <h2 id="titre-bilan" className="text-2xl">Import terminé</h2>
          <p>{status.message}</p>
          <dl className="m-0 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[
              ['Total', status.total],
              ['Nouveaux', status.imported],
              ...(status.enriched > 0 ? [['Enrichis', status.enriched]] : []),
              ['Déjà présents', status.skipped],
            ].map(([label, value]) => (
              <div key={label} className="rounded-xl border-[3px] border-ink bg-paper p-3 text-center">
                <dd className="m-0 font-display text-3xl leading-none">{value}</dd>
                <dt className="mt-1 text-sm font-bold text-stone-600">{label}</dt>
              </div>
            ))}
          </dl>
          <Link to="/ludotheque" className="btn btn-secondary self-start">Voir ma ludothèque</Link>
        </section>
      )}

      <section className="panel p-5 sm:p-6" aria-labelledby="titre-pseudo">
        <h2 id="titre-pseudo" className="mb-2 text-2xl">Trouver votre pseudo BGG</h2>
        <ol className="m-0 flex list-decimal flex-col gap-1 pl-5 text-stone-700">
          <li>Rendez-vous sur boardgamegeek.com.</li>
          <li>Connectez-vous à votre compte.</li>
          <li>Votre pseudo apparaît en haut à droite.</li>
          <li>Assurez-vous que votre collection est publique.</li>
        </ol>
      </section>
    </div>
  )
}
