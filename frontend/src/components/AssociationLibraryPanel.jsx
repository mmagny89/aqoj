import { useEffect, useState } from 'react'
import { getAssociationGames, removeAssociationGame } from '../api'
import { ErrorMessage } from './forms'

/** Ce que contient la ludothèque de l'association (administrateurs) : lecture, et retrait d'un jeu mal importé. */
export default function AssociationLibraryPanel({ refreshKey = 0 }) {
  const [data, setData]   = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    let current = true
    getAssociationGames()
      .then(result => { if (current) setData(result) })
      .catch(err => { if (current) setError(err.message || 'Impossible de charger la ludothèque.') })
    return () => { current = false }
  }, [refreshKey])

  const remove = async (game) => {
    setError(null)
    try {
      await removeAssociationGame(game.id)
      setData(prev => ({ count: prev.count - 1, games: prev.games.filter(g => g.id !== game.id) }))
    } catch (err) {
      setError(err.message || 'Impossible de retirer ce jeu.')
    }
  }

  return (
    <section aria-labelledby="titre-asso" className="panel flex flex-col gap-3 p-5 sm:p-6">
      <h2 id="titre-asso" className="text-2xl">
        Ludothèque de l’association{data ? ` (${data.count} jeu${data.count > 1 ? 'x' : ''})` : ''}
      </h2>
      <ErrorMessage>{error}</ErrorMessage>
      {data && data.count === 0 && <p className="m-0 text-stone-600">Aucun jeu pour l’instant : importez un export MyLudo ou un compte BGG.</p>}
      {data && data.count > 0 && (
        <details>
          <summary className="cursor-pointer font-bold">Voir les jeux</summary>
          <ul className="m-0 mt-2 flex list-none flex-col p-0">
            {data.games.map(game => (
              <li key={game.id} className="flex flex-wrap items-center justify-between gap-2 border-b-2 border-ink/15 py-1 last:border-b-0">
                <span className="min-w-0 break-words">
                  <strong>{game.name}</strong>
                  <span className="text-stone-600">
                    {game.yearPublished ? ` (${game.yearPublished})` : ''}
                    {game.isExpansion ? ' · extension' : ''}
                  </span>
                </span>
                <button type="button" onClick={() => remove(game)} aria-label={`Retirer ${game.name}`}
                  className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">
                  Retirer
                </button>
              </li>
            ))}
          </ul>
        </details>
      )}
    </section>
  )
}
