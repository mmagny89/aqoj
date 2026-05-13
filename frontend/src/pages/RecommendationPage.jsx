import { useState } from 'react'
import { getRecommendations } from '../api'
import GameCard from '../components/GameCard'

const AMBIANCE = [
  { id: 'strategie', label: '🧠 Stratégie' },
  { id: 'famille', label: '👨‍👩‍👧 Famille' },
  { id: 'cooperatif', label: '🤝 Coopératif' },
  { id: 'ambiance', label: '🎉 Ambiance' },
  { id: 'bluff', label: '🃏 Bluff' },
  { id: 'deduction', label: '🔍 Déduction' },
]

export default function RecommendationPage() {
  const [players, setPlayers] = useState(4)
  const [maxTime, setMaxTime] = useState(60)
  const [ambiance, setAmbiance] = useState([])
  const [results, setResults] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const toggle = (id) =>
    setAmbiance((prev) => (prev.includes(id) ? prev.filter((a) => a !== id) : [...prev, id]))

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const data = await getRecommendations({ players, maxTime, categories: ambiance })
      setResults(data)
    } catch (err) {
      setError(err.message || 'Erreur. Avez-vous importé votre collection BGG ?')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <h2 className="text-3xl font-black text-stone-900 mb-8">Ce soir on joue à…</h2>

      <form onSubmit={handleSubmit} className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-6">
        {/* Players */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-3">Combien de joueurs ?</p>
          <div className="flex gap-2 flex-wrap">
            {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
              <button
                key={n}
                type="button"
                onClick={() => setPlayers(n)}
                className={
                  'w-11 h-11 rounded-xl font-bold text-lg transition-all ' +
                  (players === n
                    ? 'bg-amber-500 text-white shadow'
                    : 'bg-stone-100 text-stone-600 hover:bg-stone-200')
                }
              >
                {n}
              </button>
            ))}
          </div>
        </div>

        {/* Time */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-3">
            Durée max :{' '}
            <span className="text-amber-600 font-bold">
              {maxTime < 60 ? `${maxTime} min` : `${maxTime / 60}h${maxTime % 60 ? maxTime % 60 : ''}`}
            </span>
          </p>
          <input
            type="range"
            min={15}
            max={180}
            step={15}
            value={maxTime}
            onChange={(e) => setMaxTime(Number(e.target.value))}
            className="w-full accent-amber-500"
          />
          <div className="flex justify-between text-xs text-stone-400 mt-1">
            <span>15 min</span>
            <span>1h</span>
            <span>1h30</span>
            <span>2h</span>
            <span>3h</span>
          </div>
        </div>

        {/* Ambiance */}
        <div>
          <p className="text-sm font-semibold text-stone-600 mb-3">Ambiance (optionnel)</p>
          <div className="flex flex-wrap gap-2">
            {AMBIANCE.map(({ id, label }) => (
              <button
                key={id}
                type="button"
                onClick={() => toggle(id)}
                className={
                  'px-3 py-1.5 rounded-full text-sm font-medium transition-all ' +
                  (ambiance.includes(id)
                    ? 'bg-amber-500 text-white'
                    : 'bg-stone-100 text-stone-600 hover:bg-stone-200')
                }
              >
                {label}
              </button>
            ))}
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold py-4 rounded-xl text-lg transition-all"
        >
          {loading ? '🎲 Recherche en cours…' : 'Recommander →'}
        </button>
      </form>

      {error && (
        <div className="mt-6 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
          {error}
        </div>
      )}

      {results !== null && results.length === 0 && (
        <div className="mt-10 text-center text-stone-500">
          <div className="text-5xl mb-4">🤷</div>
          <p className="font-medium">Aucun jeu trouvé pour ces critères.</p>
          <p className="text-sm mt-2">
            Essayez d'élargir les critères ou{' '}
            <a href="/importer" className="text-amber-600 underline">
              importez votre collection BGG
            </a>
            .
          </p>
        </div>
      )}

      {results && results.length > 0 && (
        <div className="mt-8">
          <h3 className="text-lg font-bold text-stone-700 mb-4">
            {results.length} jeu{results.length > 1 ? 'x' : ''} recommandé{results.length > 1 ? 's' : ''}
          </h3>
          <div className="space-y-3">
            {results.map(({ game, reason }, i) => (
              <GameCard key={game.id} game={game} reason={reason} rank={i + 1} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
