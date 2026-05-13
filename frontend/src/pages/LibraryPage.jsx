import { useState, useEffect } from 'react'
import { searchGames } from '../api'
import GameCard from '../components/GameCard'

export default function LibraryPage() {
  const [games, setGames] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [filters, setFilters] = useState({ players: '', maxTime: '' })

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const params = {}
      if (filters.players) params.players = filters.players
      if (filters.maxTime) params.maxTime = filters.maxTime
      const data = await searchGames(params)
      setGames(data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleFilter = (e) => {
    e.preventDefault()
    load()
  }

  const reset = () => {
    setFilters({ players: '', maxTime: '' })
    setTimeout(load, 0)
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-3xl font-black text-stone-900">Ma ludothèque</h2>
        {games.length > 0 && (
          <span className="text-sm text-stone-400">{games.length} jeux</span>
        )}
      </div>

      {/* Filters */}
      <form onSubmit={handleFilter} className="bg-white rounded-xl border border-stone-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-semibold text-stone-500 mb-1">Joueurs</label>
          <select
            value={filters.players}
            onChange={(e) => setFilters((f) => ({ ...f, players: e.target.value }))}
            className="px-3 py-2 rounded-lg border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
          >
            <option value="">Tous</option>
            {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
              <option key={n} value={n}>{n} joueurs</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-semibold text-stone-500 mb-1">Durée max</label>
          <select
            value={filters.maxTime}
            onChange={(e) => setFilters((f) => ({ ...f, maxTime: e.target.value }))}
            className="px-3 py-2 rounded-lg border border-stone-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
          >
            <option value="">Toutes</option>
            <option value="30">30 min</option>
            <option value="60">1h</option>
            <option value="90">1h30</option>
            <option value="120">2h</option>
            <option value="180">3h</option>
          </select>
        </div>
        <button
          type="submit"
          className="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600"
        >
          Filtrer
        </button>
        {(filters.players || filters.maxTime) && (
          <button type="button" onClick={reset} className="text-sm text-stone-400 hover:text-stone-600">
            Réinitialiser
          </button>
        )}
      </form>

      {loading && (
        <div className="text-center py-12 text-stone-400">
          <div className="text-4xl animate-spin mb-4">⏳</div>
          <p>Chargement…</p>
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
          {error}
        </div>
      )}

      {!loading && !error && games.length === 0 && (
        <div className="text-center py-12 text-stone-500">
          <div className="text-5xl mb-4">📚</div>
          <p className="font-medium">Votre ludothèque est vide.</p>
          <p className="text-sm mt-2">
            <a href="/importer" className="text-amber-600 hover:underline">
              Importez votre collection BGG →
            </a>
          </p>
        </div>
      )}

      {!loading && games.length > 0 && (
        <div className="space-y-3">
          {games.map((game) => (
            <GameCard key={game.id} game={game} />
          ))}
        </div>
      )}
    </div>
  )
}
