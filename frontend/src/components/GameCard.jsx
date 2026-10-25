import { Link } from 'react-router-dom'
import { familyLabel, familyColor, SUPPORT_COLOR, isEngine } from '../utils/engelstein'

/* Fond de la pastille d'un jeu sans image : une couleur de la charte, stable pour un même jeu. */
const COVER_COLORS = ['bg-brand-red', 'bg-brand-yellow', 'bg-[#8fbf9a]', 'bg-board-deep']

export function coverColor(game) {
  const seed = Number(game.id) || String(game.name ?? '').length
  return COVER_COLORS[seed % COVER_COLORS.length]
}

export function initials(name = '') {
  const words = name.replace(/[^\p{L}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean)
  if (words.length === 0) return '?'
  return words.length === 1 ? words[0].slice(0, 2) : words[0].charAt(0) + words[1].charAt(0)
}

export function weightDots(complexity) {
  const level = Math.max(0, Math.min(5, Math.round(complexity)))
  return '●'.repeat(level) + '○'.repeat(5 - level)
}

export default function GameCard({ game, reason, rank, onAddSession, userRating, owned, similarity }) {
  const playerRange =
    game.minPlayers === game.maxPlayers
      ? game.minPlayers
      : `${game.minPlayers}–${game.maxPlayers}`

  /* Badges mécaniques */
  const engines  = game.displayEngines ?? []
  const fallback = engines.length === 0
    ? (game.mechanicFamilies ?? []).filter(f => !isEngine(f)).slice(0, 2)
    : []
  const badges = engines.length > 0 ? engines : fallback

  return (
    <article className="token-card group relative h-full">
      {/* ── Image ────────────────────────────────────────────────────── */}
      <div className={`relative aspect-4/3 shrink-0 overflow-hidden border-b-[3px] border-ink ${game.imageUrl ? 'bg-cream' : coverColor(game)}`}>
        {game.imageUrl ? (
          <img
            src={game.imageUrl}
            alt=""
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="grid h-full w-full place-items-center font-display text-5xl" aria-hidden="true">
            {initials(game.name)}
          </div>
        )}

        {rank != null && (
          <span className="badge absolute left-2.5 top-2.5 bg-paper">#{rank}</span>
        )}

        {/* Note BGG, ou % de ressemblance quand il est fourni */}
        {similarity != null ? (
          <span className="badge absolute right-2.5 top-2.5 bg-brand-yellow">{similarity}%</span>
        ) : game.ratingBgg != null ? (
          <span className="badge absolute right-2.5 top-2.5 bg-paper">★ {game.ratingBgg.toFixed(1)}</span>
        ) : null}

        {owned != null && (
          <span className={`badge absolute bottom-2.5 left-2.5 ${owned ? 'bg-brand-green text-paper' : 'bg-paper'}`}>
            {owned ? '✓ Possédé' : 'Non possédé'}
          </span>
        )}

        {userRating != null && (
          <span className="badge absolute bottom-2.5 right-2.5 bg-brand-yellow">Ma note {userRating}/10</span>
        )}
      </div>

      {/* ── Contenu ───────────────────────────────────────────────────── */}
      <div className="flex flex-1 flex-col gap-2 p-3.5">
        <div>
          <h3 className="text-base font-bold leading-tight line-clamp-2">
            {/* Lien étiré sur toute la carte : une seule cible, et le bouton d'action reste cliquable au-dessus. */}
            <Link to={`/games/${game.id}`} className="after:absolute after:inset-0 after:content-[''] focus-visible:after:rounded-2xl">
              {game.name}
            </Link>
          </h3>
          <div className="mt-0.5 flex flex-wrap items-center gap-1.5">
            {game.yearPublished && <span className="text-sm text-stone-500">{game.yearPublished}</span>}
            {game.isExpansion && <span className="badge bg-cream">Extension</span>}
          </div>
        </div>

        <p className="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-stone-600">
          {playerRange && <span>{playerRange} joueurs</span>}
          {game.playingTime > 0 && <span>{game.playingTime} min</span>}
          {game.complexity > 0 && (
            <span title={`Complexité ${game.complexity.toFixed(1)} sur 5`}>
              <span aria-hidden="true" className="tracking-wider">{weightDots(game.complexity)}</span>
              <span className="sr-only">Complexité {game.complexity.toFixed(1)} sur 5</span>
            </span>
          )}
        </p>

        {badges.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {badges.map(f => (
              <span
                key={f}
                className={`rounded-full border px-2 py-0.5 text-xs font-medium ${
                  engines.length > 0 ? familyColor(f) : SUPPORT_COLOR
                }`}
              >
                {familyLabel(f)}
              </span>
            ))}
          </div>
        )}

        {reason && (
          <p className="rounded-lg bg-amber-100 px-2.5 py-1.5 text-xs italic leading-snug text-amber-800">
            {reason}
          </p>
        )}

        {onAddSession && (
          <button
            type="button"
            onClick={() => onAddSession(game)}
            className="relative z-10 mt-auto inline-flex min-h-11 items-center text-left text-sm font-bold text-brand-blue underline underline-offset-4"
          >
            + Enregistrer une partie
          </button>
        )}
      </div>
    </article>
  )
}
