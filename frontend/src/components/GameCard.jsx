import { useNavigate } from 'react-router-dom'

export default function GameCard({ game, reason, rank, onAddSession, compact = false }) {
  const navigate = useNavigate()

  const playerRange =
    game.minPlayers === game.maxPlayers
      ? game.minPlayers
      : `${game.minPlayers}–${game.maxPlayers}`

  return (
    <div
      onClick={() => navigate(`/games/${game.id}`)}
      className="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden flex cursor-pointer hover:border-amber-300 hover:shadow-md transition-all"
    >
      {game.imageUrl ? (
        <img
          src={game.imageUrl}
          alt={game.name}
          className="w-24 h-24 sm:w-32 sm:h-32 object-cover flex-shrink-0 self-center"
        />
      ) : (
        <div className="w-24 sm:w-32 flex-shrink-0 bg-stone-100 flex items-center justify-center text-3xl self-center h-24 sm:h-32">
          🎲
        </div>
      )}
      <div className="p-3 sm:p-4 flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            {rank != null && (
              <span className="text-xs font-bold text-amber-500 block">#{rank}</span>
            )}
            <h3 className="font-bold text-stone-900 leading-tight">{game.name}</h3>
            {game.yearPublished && (
              <span className="text-xs text-stone-400">{game.yearPublished}</span>
            )}
          </div>
          {game.ratingBgg != null && (
            <div className="flex-shrink-0 text-center bg-amber-50 rounded-lg px-2 py-1">
              <div className="text-sm font-bold text-amber-700">{game.ratingBgg.toFixed(1)}</div>
              <div className="text-xs text-stone-400">BGG</div>
            </div>
          )}
        </div>

        <div className="flex flex-wrap gap-3 mt-2 text-xs text-stone-500">
          <span>👥 {playerRange}</span>
          {game.playingTime > 0 && <span>⏱ {game.playingTime} min</span>}
          {game.complexity > 0 && <span>🧠 {game.complexity.toFixed(1)}/5</span>}
        </div>

        {reason && (
          <p className="mt-2 text-xs italic text-amber-800 bg-amber-50 rounded-lg px-2 py-1.5">
            {reason}
          </p>
        )}

        {onAddSession && (
          <button
            onClick={(e) => { e.stopPropagation(); onAddSession(game) }}
            className="mt-2 text-xs text-stone-500 hover:text-amber-600 hover:underline"
          >
            + Enregistrer une partie
          </button>
        )}
      </div>
    </div>
  )
}
