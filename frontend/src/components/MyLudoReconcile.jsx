import { useId, useState } from 'react'
import GamePicker from './GamePicker'

export const formatNote = (n) => String(n).replace('.', ',')

/** « Nom (2019) · 7,5/10 » : de quoi reconnaître un jeu parmi des homonymes. */
export function describeGame(game) {
  return [
    game.yearPublished ? `${game.name} (${game.yearPublished})` : game.name,
    game.ratingBgg ? `${formatNote(game.ratingBgg.toFixed(1))}/10` : null,
    game.usersRated ? `${game.usersRated.toLocaleString('fr-FR')} votes` : null,
  ].filter(Boolean).join(' · ')
}

export function RowTitle({ row }) {
  return (
    <div>
      <h4 className="m-0 text-lg font-bold">
        {row.title}{row.subtitle ? <span className="font-normal"> — {row.subtitle}</span> : null}
      </h4>
      <p className="m-0 text-sm text-stone-600">
        {[row.year, row.publisher, row.rating ? `votre note : ${formatNote(row.rating)}/10` : null, row.plays ? `${row.plays} partie${row.plays > 1 ? 's' : ''}` : null].filter(Boolean).join(' · ')}
      </p>
    </div>
  )
}

/* ── Une ligne à rapprocher : on choisit le bon jeu, on cherche un autre, ou on laisse de côté ── */
export function ReconcileRow({ row, choice, onChoose }) {
  const name = useId()
  const [searching, setSearching] = useState(row.status === 'unmatched')

  return (
    <li className="panel flex flex-col gap-3 p-4">
      <RowTitle row={row} />

      {row.status === 'ambiguous' && (
        <fieldset className="m-0 flex flex-col gap-1 border-0 p-0">
          <legend className="mb-1 font-bold">Plusieurs jeux portent ce nom : lequel est-ce ?</legend>
          {row.candidates.map(game => (
            <label key={game.id} className="flex min-h-11 cursor-pointer items-center gap-3">
              <input type="radio" name={name} checked={choice?.id === game.id}
                onChange={() => { setSearching(false); onChoose(game) }} className="h-5 w-5 accent-brand-red" />
              <span>{describeGame(game)}</span>
            </label>
          ))}
          <label className="flex min-h-11 cursor-pointer items-center gap-3">
            <input type="radio" name={name} checked={searching}
              onChange={() => { setSearching(true); onChoose(null) }} className="h-5 w-5 accent-brand-red" />
            <span>Un autre jeu : je le cherche</span>
          </label>
          <label className="flex min-h-11 cursor-pointer items-center gap-3">
            <input type="radio" name={name} checked={!searching && choice === null}
              onChange={() => { setSearching(false); onChoose(null) }} className="h-5 w-5 accent-brand-red" />
            <span>Ne pas importer ce jeu</span>
          </label>
        </fieldset>
      )}

      {(searching || row.status === 'unmatched') && (
        <>
          {row.status === 'unmatched' && (
            <p className="m-0 text-stone-600">Ce jeu n'est pas reconnu : cherchez-le dans le catalogue, ou laissez-le de côté.</p>
          )}
          <GamePicker label="Chercher dans le catalogue" value={choice} onChange={onChoose} />
        </>
      )}
    </li>
  )
}

/* ── Une ligne déjà rapprochée : on peut la décocher ou changer de jeu ── */
export function MatchedRow({ row, choice, onChoose }) {
  const [changing, setChanging] = useState(false)

  return (
    <li className="flex flex-col gap-2 border-b-2 border-ink/15 py-3 last:border-b-0">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <label className="flex min-h-11 min-w-0 cursor-pointer items-center gap-3">
          <input type="checkbox" checked={choice !== null && !changing}
            onChange={(e) => onChoose(e.target.checked ? row.game : null)} disabled={changing}
            className="h-5 w-5 shrink-0 accent-brand-red" />
          <span className="min-w-0 break-words">
            <strong>{row.title}</strong>
            {row.year ? <span className="text-stone-600"> ({row.year})</span> : null}
            <span className="text-stone-600"> → {describeGame(choice ?? row.game)}</span>
          </span>
        </label>
        <button type="button" onClick={() => setChanging(v => !v)}
          className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">
          {changing ? 'Garder ce jeu' : 'Ce n’est pas ce jeu'}
        </button>
      </div>
      {changing && <GamePicker label="Chercher le bon jeu" value={choice?.id === row.game.id ? null : choice} onChange={(g) => onChoose(g ?? row.game)} />}
    </li>
  )
}

