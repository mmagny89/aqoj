import { useNavigate } from 'react-router-dom'

export default function HomePage() {
  const navigate = useNavigate()

  return (
    <div className="flex flex-col items-center justify-center min-h-[calc(100vh-4rem)] px-4 text-center">
      <div className="text-8xl mb-6">🎲</div>

      <h1 className="text-4xl sm:text-6xl font-black text-stone-900 mb-4 leading-tight">
        À quoi on joue ?
      </h1>

      <p className="text-lg text-stone-500 mb-10 max-w-sm">
        Trouvez le jeu parfait pour ce soir en moins de 30 secondes.
      </p>

      <div className="flex flex-col sm:flex-row gap-3 w-full max-w-sm">
        <button
          onClick={() => navigate('/recommander')}
          className="flex-1 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-lg font-bold px-6 py-4 rounded-2xl shadow-xl transition-all"
        >
          Ce soir on joue →
        </button>
        <button
          onClick={() => navigate('/rechercher')}
          className="flex-1 bg-white hover:bg-stone-50 active:scale-95 text-stone-700 text-lg font-bold px-6 py-4 rounded-2xl shadow border border-stone-200 transition-all"
        >
          🔍 Chercher
        </button>
      </div>

      <div className="mt-16 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm text-stone-500 max-w-lg">
        {[
          { icon: '🔍', text: 'Chercher un jeu', to: '/rechercher' },
          { icon: '✨', text: 'Recommandations', to: '/recommander' },
          { icon: '📚', text: 'Ma ludothèque', to: '/ludotheque' },
          { icon: '📊', text: 'Mes parties', to: '/parties' },
        ].map(({ icon, text, to }) => (
          <button
            key={to}
            onClick={() => navigate(to)}
            className="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-white hover:shadow-sm transition-all cursor-pointer"
          >
            <span className="text-3xl">{icon}</span>
            <span>{text}</span>
          </button>
        ))}
      </div>
    </div>
  )
}
