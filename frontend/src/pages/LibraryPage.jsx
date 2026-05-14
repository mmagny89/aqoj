import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { getLibrary } from '../api'
import { useAuth } from '../context/AuthContext'
import GameCard from '../components/GameCard'

const LIMIT = 50

export default function LibraryPage() {
  const { user } = useAuth()
  const [games, setGames] = useState([])
  const [bggUsername, setBggUsername] = useState(null)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(true)
  const [total, setTotal] = useState(null)
  const [loading, setLoading] = useState(false)
  const [initialLoading, setInitialLoading] = useState(true)
  const [error, setError] = useState(null)
  const sentinelRef = useRef(null)

  const fetchPage = useCallback(async (pageNum, replace) => {
    setLoading(true)
    try {
      const data = await getLibrary(pageNum)
      setBggUsername(data.bggUsername)
      setTotal(data.total)
      setGames(prev => replace ? data.games : [...prev, ...data.games])
      setHasMore(data.hasMore)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
      if (replace) setInitialLoading(false)
    }
  }, [])

  useEffect(() => {
    if (user) {
      setInitialLoading(true)
      setPage(1)
      fetchPage(1, true)
    } else {
      setInitialLoading(false)
    }
  }, [user, fetchPage])

  useEffect(() => {
    if (page > 1) fetchPage(page, false)
  }, [page]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting && hasMore && !loading) setPage(p => p + 1) },
      { rootMargin: '200px' }
    )
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [hasMore, loading])

  if (!user) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-8 text-center">
        <div className="text-5xl mb-4">📚</div>
        <h2 className="text-2xl font-black text-stone-900 mb-2">Ma ludothèque</h2>
        <p className="text-stone-500 mb-6">Connecte-toi pour accéder à ta collection personnelle.</p>
        <Link to="/connexion" className="px-6 py-3 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600">
          Se connecter
        </Link>
      </div>
    )
  }

  if (initialLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
      </div>
    )
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-black text-stone-900">Ma ludothèque</h2>
          {bggUsername && (
            <p className="text-sm text-stone-400 mt-0.5">
              Collection BGG de <span className="font-medium text-stone-600">{bggUsername}</span>
            </p>
          )}
        </div>
        {total !== null && (
          <span className="text-sm text-stone-400">{total} jeu{total > 1 ? 'x' : ''}</span>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-6">
          {error}
        </div>
      )}

      {!error && games.length === 0 && !loading && (
        <div className="text-center py-12 text-stone-500">
          <div className="text-5xl mb-4">📭</div>
          <p className="font-medium text-stone-700 mb-2">Ta collection est vide.</p>
          <p className="text-sm text-stone-500 mb-6">
            Importe ta collection BoardGameGeek pour la retrouver ici.
          </p>
          <Link
            to="/importer"
            className="px-6 py-3 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600 inline-block"
          >
            Importer depuis BGG →
          </Link>
        </div>
      )}

      {games.length > 0 && (
        <div className="space-y-3">
          {games.map((game) => (
            <GameCard key={game.id} game={game} />
          ))}
        </div>
      )}

      <div ref={sentinelRef} className="h-4" />

      {loading && !initialLoading && (
        <div className="text-center py-6">
          <div className="inline-block w-5 h-5 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
        </div>
      )}

      {!hasMore && games.length > 0 && (
        <p className="text-center text-xs text-stone-400 py-4">
          {total ?? games.length} jeu{(total ?? games.length) > 1 ? 'x' : ''} dans ta collection
        </p>
      )}
    </div>
  )
}
