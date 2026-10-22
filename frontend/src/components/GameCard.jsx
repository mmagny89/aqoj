import { useNavigate } from 'react-router-dom'
import { familyLabel, familyColor, SUPPORT_COLOR, isEngine } from '../utils/engelstein'

export default function GameCard({ game, reason, rank, onAddSession, userRating, owned, similarity }) {
  const navigate = useNavigate()

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
    <div
      onClick={() => navigate(`/games/${game.id}`)}
      className="group bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden flex flex-col cursor-pointer hover:border-amber-300 hover:shadow-lg transition-all duration-200"
    >
      {/* ── Image ────────────────────────────────────────────────────── */}
      <div className="relative aspect-square bg-stone-100 flex-shrink-0 overflow-hidden">
        {game.imageUrl ? (
          <img
            src={game.imageUrl}
            alt={game.name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-4xl text-stone-300">
            🎲
          </div>
        )}

        {/* Note BGG — coin sup. droit (ou % similarité si fourni) */}
        {similarity != null ? (
          <div className="absolute top-2 right-2 bg-amber-500 text-white text-xs font-black px-2 py-1 rounded-lg leading-tight text-center shadow">
            {similarity}%
          </div>
        ) : game.ratingBgg != null ? (
          <div className="absolute top-2 right-2 bg-black/60 backdrop-blur-sm text-white text-xs font-bold px-2 py-1 rounded-lg leading-tight text-center">
            <span className="text-amber-300">{game.ratingBgg.toFixed(1)}</span>
            <span className="block text-[10px] opacity-70 font-normal">BGG</span>
          </div>
        ) : null}

        {/* Rang — coin sup. gauche */}
        {rank != null && (
          <div className="absolute top-2 left-2 bg-amber-500 text-white text-xs font-black px-2 py-0.5 rounded-lg">
            #{rank}
          </div>
        )}

        {/* Badge possédé — coin inf. gauche */}
        {owned != null && (
          <div className={`absolute bottom-2 left-2 text-xs font-bold px-2 py-0.5 rounded-full backdrop-blur-sm border ${
            owned
              ? 'bg-emerald-500/90 text-white border-emerald-400'
              : 'bg-black/50 text-stone-300 border-stone-500'
          }`}>
            {owned ? '✓ Possédé' : 'Non possédé'}
          </div>
        )}

        {/* Ma note — coin inf. droit */}
        {userRating != null && (
          <div className="absolute bottom-2 right-2 bg-emerald-500/90 backdrop-blur-sm text-white text-xs font-bold px-2 py-1 rounded-lg leading-tight text-center">
            <span>{userRating}/10</span>
            <span className="block text-[10px] opacity-80 font-normal">ma note</span>
          </div>
        )}
      </div>

      {/* ── Contenu ───────────────────────────────────────────────────── */}
      <div className="p-3 flex flex-col gap-2 flex-1">

        {/* Titre + année */}
        <div>
          <h3 className="font-bold text-stone-900 text-sm leading-tight line-clamp-2">
            {game.name}
          </h3>
          <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
            {game.yearPublished && (
              <span className="text-xs text-stone-400">{game.yearPublished}</span>
            )}
            {game.isExpansion && (
              <span className="text-[10px] bg-stone-100 text-stone-500 border border-stone-200 px-1.5 py-0.5 rounded-full font-medium leading-none">
                Extension
              </span>
            )}
          </div>
        </div>

        {/* Méta : joueurs / durée / complexité */}
        <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-stone-500">
          {playerRange && <span>👥 {playerRange}</span>}
          {game.playingTime > 0 && <span>⏱ {game.playingTime} min</span>}
          {game.complexity > 0 && <span>🧠 {game.complexity.toFixed(1)}</span>}
        </div>

        {/* Badges mécaniques */}
        {badges.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {badges.map(f => (
              <span
                key={f}
                className={`text-xs px-1.5 py-0.5 rounded-full border font-medium ${
                  engines.length > 0 ? familyColor(f) : SUPPORT_COLOR
                }`}
              >
                {familyLabel(f)}
              </span>
            ))}
          </div>
        )}

        {/* Raison de recommandation */}
        {reason && (
          <p className="text-xs italic text-amber-800 bg-amber-50 rounded-lg px-2 py-1.5 leading-snug">
            {reason}
          </p>
        )}

        {/* Enregistrer une partie */}
        {onAddSession && (
          <button
            onClick={(e) => { e.stopPropagation(); onAddSession(game) }}
            className="mt-auto text-xs text-stone-400 hover:text-amber-600 hover:underline text-left"
          >
            + Enregistrer une partie
          </button>
        )}
      </div>
    </div>
  )
}
