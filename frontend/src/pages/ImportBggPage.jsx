import { useState } from 'react'
import { importBgg } from '../api'

export default function ImportBggPage() {
  const [username, setUsername] = useState('')
  const [status, setStatus] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const handleImport = async (e) => {
    e.preventDefault()
    if (!username.trim()) return
    setLoading(true)
    setError(null)
    setStatus(null)
    try {
      const data = await importBgg(username.trim())
      setStatus(data)
    } catch (err) {
      setError(err.message || 'Erreur lors de l\'import')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-lg mx-auto px-4 py-8">
      <h2 className="text-3xl font-black text-stone-900 mb-2">Importer depuis BGG</h2>
      <p className="text-stone-500 mb-8">
        Importez votre collection BoardGameGeek pour obtenir des recommandations personnalisées.
      </p>

      <form onSubmit={handleImport} className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-4">
        <div>
          <label className="block text-sm font-semibold text-stone-600 mb-2">
            Votre pseudo BoardGameGeek
          </label>
          <input
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            placeholder="ex: Kikigamer42"
            className="w-full px-4 py-3 rounded-xl border border-stone-300 focus:outline-none focus:ring-2 focus:ring-amber-400 text-stone-900"
          />
        </div>

        <button
          type="submit"
          disabled={loading || !username.trim()}
          className="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition-all"
        >
          {loading ? (
            <span className="flex items-center justify-center gap-2">
              <span className="animate-spin">⏳</span> Import en cours… (peut prendre 30 sec)
            </span>
          ) : (
            'Importer ma collection →'
          )}
        </button>
      </form>

      {error && (
        <div className="mt-4 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
          {error}
        </div>
      )}

      {status && (
        <div className="mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
          <div className="text-green-800 font-bold mb-1">✅ Import terminé !</div>
          <p className="text-green-700 text-sm">{status.message}</p>
          <div className="mt-3 grid grid-cols-3 gap-3 text-center text-sm">
            <div className="bg-white rounded-lg p-3 shadow-sm">
              <div className="font-bold text-xl text-stone-900">{status.total}</div>
              <div className="text-stone-400">Total collection</div>
            </div>
            <div className="bg-white rounded-lg p-3 shadow-sm">
              <div className="font-bold text-xl text-green-600">{status.imported}</div>
              <div className="text-stone-400">Nouveaux jeux</div>
            </div>
            <div className="bg-white rounded-lg p-3 shadow-sm">
              <div className="font-bold text-xl text-stone-400">{status.skipped}</div>
              <div className="text-stone-400">Déjà importés</div>
            </div>
          </div>
          <a href="/ludotheque" className="mt-4 block text-center text-amber-600 hover:underline text-sm">
            Voir ma ludothèque →
          </a>
        </div>
      )}

      <div className="mt-8 bg-stone-100 rounded-xl p-4 text-sm text-stone-500">
        <p className="font-semibold text-stone-700 mb-2">ℹ️ Comment trouver votre pseudo BGG ?</p>
        <ol className="list-decimal list-inside space-y-1">
          <li>Rendez-vous sur boardgamegeek.com</li>
          <li>Connectez-vous à votre compte</li>
          <li>Votre pseudo apparaît en haut à droite</li>
          <li>Assurez-vous que votre collection est publique</li>
        </ol>
      </div>
    </div>
  )
}
