import { useEffect, useId, useState } from 'react'
import { searchGames } from '../api'

/**
 * Choisir un jeu parmi toute la base : on tape, on choisit dans la liste. La recherche reste locale
 * (`local`) : taper des lettres n'appelle jamais BoardGameGeek.
 */
export default function GamePicker({ value, onChange, label = 'Jeu joué' }) {
  const id = useId()
  const [term, setTerm] = useState('')
  const [results, setResults] = useState([])
  const [searching, setSearching] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    const query = term.trim()
    if (query.length < 2) return undefined

    let cancelled = false
    const timer = setTimeout(() => {
      setSearching(true)
      setError(null)
      searchGames({ q: query, local: true })
        .then(games => { if (!cancelled) setResults(games.slice(0, 8)) })
        .catch(e => { if (!cancelled) setError(e.message) })
        .finally(() => { if (!cancelled) setSearching(false) })
    }, 300)

    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [term])

  // Sous deux lettres, aucun résultat ne s'affiche, même si une recherche précédente en a laissé.
  const shown = term.trim().length >= 2 ? results : []

  if (value) {
    return (
      <div>
        <p className="mb-1 font-bold">{label}</p>
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border-[3px] border-ink bg-cream px-4 py-2">
          <strong className="min-w-0 break-words">
            {value.name}
            {value.yearPublished ? <span className="ml-2 font-normal text-stone-600">({value.yearPublished})</span> : null}
          </strong>
          <button type="button" onClick={() => { onChange(null); setTerm('') }} className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">
            Changer de jeu
          </button>
        </div>
      </div>
    )
  }

  return (
    <div>
      <label htmlFor={id} className="mb-1 block font-bold">{label}</label>
      <input
        id={id}
        type="search"
        value={term}
        onChange={e => setTerm(e.target.value)}
        placeholder="Tapez le nom du jeu : Azul, Catan…"
        autoComplete="off"
        aria-describedby={`${id}-aide`}
        className="min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper px-4 text-base placeholder:text-stone-400"
      />
      <p id={`${id}-aide`} className="mt-1 text-sm text-stone-600" role="status" aria-live="polite">
        {searching
          ? 'Recherche…'
          : error
            ? error
            : term.trim().length >= 2 && shown.length === 0
              ? 'Aucun jeu trouvé : essayez le titre en anglais.'
              : shown.length > 0 ? `${shown.length} résultat${shown.length > 1 ? 's' : ''}` : 'Au moins deux lettres.'}
      </p>
      {shown.length > 0 && (
        <ul className="m-0 mt-2 flex list-none flex-col overflow-hidden rounded-xl border-[3px] border-ink bg-paper p-0">
          {shown.map(game => (
            <li key={game.id} className="border-b-2 border-ink/15 last:border-b-0">
              <button
                type="button"
                onClick={() => { onChange(game); setTerm(''); setResults([]) }}
                className="flex min-h-11 w-full items-center justify-between gap-3 px-4 py-2 text-left hover:bg-amber-100"
              >
                <span className="min-w-0 break-words font-bold">{game.name}</span>
                <span className="shrink-0 text-sm text-stone-600">
                  {game.isExpansion ? 'Extension' : game.yearPublished ?? ''}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
