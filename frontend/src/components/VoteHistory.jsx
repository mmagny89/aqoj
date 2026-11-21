import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getFeedbackHistory } from '../api'
import { useGameFeedback } from '../hooks/useGameFeedback'
import { FeedbackButtons, FeedbackNotice, VERDICT_LABELS } from './GameFeedback'
import { ErrorMessage } from './forms'

const FILTERS = [{ id: 'all', label: 'Tous' }, ...Object.entries(VERDICT_LABELS).map(([id, label]) => ({ id, label }))]
const dateOf = (iso) => (iso ? new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : '')

/* Historique des votes : on change le vote avec les mêmes boutons que partout, et rappuyer sur le vote actif l'annule. */
export default function VoteHistory() {
  const feedback = useGameFeedback()
  const [votes, setVotes] = useState(null)
  const [error, setError] = useState(null)
  const [filter, setFilter] = useState('all')

  useEffect(() => {
    let alive = true
    getFeedbackHistory()
      .then(data => alive && setVotes(data))
      .catch(e => alive && setError(e.message))
    return () => { alive = false }
  }, [])

  if (error) return <ErrorMessage>{error}</ErrorMessage>
  if (votes === null) return <p role="status" className="text-stone-600">Chargement…</p>

  // Le vote courant : celui modifié ici, sinon celui du serveur ; null = annulé.
  const rows = votes
    .map(v => ({ ...v, current: feedback.verdictOf(v.game.id, v.verdict) }))
    .filter(v => v.current !== null)
  const count = (id) => (id === 'all' ? rows.length : rows.filter(v => v.current === id).length)
  const shown = filter === 'all' ? rows : rows.filter(v => v.current === filter)

  return (
    <div className="flex flex-col gap-4">
      <div role="group" aria-label="Filtrer mes votes" className="flex flex-wrap gap-2">
        {FILTERS.map(f => (
          <button key={f.id} type="button" aria-pressed={filter === f.id} onClick={() => setFilter(f.id)}
            className="chip min-h-11 cursor-pointer px-3 aria-pressed:bg-brand-yellow">
            {f.label} ({count(f.id)})
          </button>
        ))}
      </div>

      {rows.length === 0 ? (
        <p className="text-stone-600">Vous n’avez encore voté sur aucun jeu. Les boutons des propositions de l’accueil vous permettent de le faire.</p>
      ) : shown.length === 0 ? (
        <p className="text-stone-600">Aucun vote dans cette catégorie.</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3 p-0">
          {shown.map(v => (
            <li key={v.game.id} className="flex flex-col gap-1 rounded-xl border-[3px] border-ink bg-paper p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
              <div className="min-w-0">
                <Link to={`/games/${v.game.id}`} className="flex min-h-11 items-center break-words font-bold underline-offset-4 hover:underline">{v.game.name}</Link>
                <p className="m-0 text-sm text-stone-600">
                  {VERDICT_LABELS[v.current]}
                  {v.source === 'auto' && v.current === v.verdict ? ' · déduit de vos parties et notes' : ''}
                  {v.votedAt && v.current === v.verdict ? ` · ${dateOf(v.votedAt)}` : ''}
                </p>
              </div>
              <div className="sm:w-56 sm:shrink-0">
                <FeedbackButtons game={v.game} verdict={v.current} onFeedback={feedback.send} />
              </div>
            </li>
          ))}
        </ul>
      )}
      <p className="m-0 text-sm text-stone-600">Appuyez sur le vote actif pour l’annuler ; un autre bouton le remplace.</p>
      <FeedbackNotice feedback={feedback} />
    </div>
  )
}
