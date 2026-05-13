import { useState, useEffect, useCallback } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { searchGames } from '../api'
import GameCard from '../components/GameCard'

function useDebounce(value, delay) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

export default function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()

  const [query, setQuery] = useState(searchParams.get('q') || '')
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(false)
  const [searched, setSearched] = useState(false)
  const [error, setError] = useState(null)

  const debouncedQuery = useDebounce(query, 400)

  const doSearch = useCallback(async (q) => {
    if (!q.trim()) {
      setResults([])
      setSearched(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const data = await searchGames({ q: q.trim() })
      setResults(data)
      setSearched(true)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    doSearch(debouncedQuery)
    if (debouncedQuery) {
      setSearchParams({ q: debouncedQuery }, { replace: true })
    }
  }, [debouncedQuery, doSearch, setSearchParams])

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <h2 className="text-3xl font-black text-stone-900 mb-6">Rechercher un jeu</h2>

      <div className="relative mb-8">
        <input
          type="search"
          autoFocus
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Nom du jeu… ex: Azul, Catan, Pandemic"
          className="w-full px-5 py-4 text-lg rounded-2xl border-2 border-stone-300 focus:outline-none focus:border-amber-400 bg-white shadow-sm"
        />
        {loading && (
          <div className="absolute right-4 top-1/2 -translate-y-1/2 text-stone-400 animate-spin text-xl">
            ⏳
          </div>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-4">
          {error}
        </div>
      )}

      {!query.trim() && (
        <div className="text-center py-12 text-stone-400">
          <div className="text-5xl mb-4">🔍</div>
          <p>Tapez le nom d'un jeu pour commencer</p>
          <p className="text-sm mt-2">Les jeux sont recherchés localement, puis sur BGG si nécessaire</p>
        </div>
      )}

      {searched && results.length === 0 && !loading && (
        <div className="text-center py-12 text-stone-500">
          <div className="text-5xl mb-4">🤷</div>
          <p className="font-medium">Aucun résultat pour "{query}"</p>
          <p className="text-sm mt-2">Vérifiez l'orthographe ou essayez en anglais</p>
        </div>
      )}

      {results.length > 0 && (
        <div>
          <p className="text-sm text-stone-400 mb-3">{results.length} résultat{results.length > 1 ? 's' : ''}</p>
          <div className="space-y-3">
            {results.map((game) => (
              <GameCard key={game.id} game={game} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
