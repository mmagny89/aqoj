import { NavLink, useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { useAuth } from '../context/AuthContext'

export default function Navigation() {
  const { user, logout } = useAuth()
  const [query, setQuery] = useState('')
  const navigate = useNavigate()

  const handleSearch = (e) => {
    e.preventDefault()
    if (query.trim()) {
      navigate(`/rechercher?q=${encodeURIComponent(query.trim())}`)
      setQuery('')
    }
  }

  const handleLogout = () => {
    logout()
    navigate('/')
  }

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-b border-stone-200 h-16 flex items-center px-4 gap-3">

      {/* Logo */}
      <NavLink
        to="/"
        end
        className={({ isActive }) =>
          'font-black text-lg whitespace-nowrap ' +
          (isActive ? 'text-amber-600' : 'text-stone-800 hover:text-amber-600')
        }
      >
        🎲 AQOJ
      </NavLink>

      {/* Barre de recherche */}
      <form onSubmit={handleSearch} className="flex-1 max-w-sm">
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Rechercher un jeu…"
          className="w-full px-3 py-1.5 text-sm rounded-lg border border-stone-300 focus:outline-none focus:ring-2 focus:ring-amber-400 bg-stone-50"
        />
      </form>

      <div className="flex items-center gap-1 ml-auto">

        {/* ── Menu standard ──────────────────────────────────────────── */}
        {[
          { to: '/recommander', label: '✨', title: 'Ce soir on joue' },
          { to: '/ludotheque',  label: '📚', title: 'Ludothèque' },
          { to: '/parties',     label: '📊', title: 'Mes parties' },
          { to: '/importer',    label: '⬇',  title: 'Importer BGG' },
          { to: '/aide',        label: '❓',  title: 'Aide' },
        ].map(({ to, label, title }) => (
          <NavLink
            key={to}
            to={to}
            title={title}
            className={({ isActive }) =>
              'px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors ' +
              (isActive ? 'bg-amber-100 text-amber-700' : 'text-stone-600 hover:bg-stone-100')
            }
          >
            {label}
          </NavLink>
        ))}

        {/* ── Menu admin — séparé visuellement ───────────────────────── */}
        {user?.isAdmin && (
          <div className="flex items-center gap-1 ml-2 pl-2 border-l border-stone-200">
            <span className="text-xs text-stone-400 font-semibold uppercase tracking-wide mr-1 hidden sm:inline">
              Admin
            </span>
            <NavLink
              to="/admin"
              end
              title="Tableau de bord"
              className={({ isActive }) =>
                'px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors ' +
                (isActive
                  ? 'bg-violet-100 text-violet-700'
                  : 'text-violet-500 hover:bg-violet-50 hover:text-violet-700')
              }
            >
              🛠
            </NavLink>
            <NavLink
              to="/admin/mecaniques"
              title="Mappings mécaniques"
              className={({ isActive }) =>
                'px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors ' +
                (isActive
                  ? 'bg-violet-100 text-violet-700'
                  : 'text-violet-500 hover:bg-violet-50 hover:text-violet-700')
              }
            >
              ⚙
            </NavLink>
            <NavLink
              to="/admin/themes"
              title="Taxonomie thématique"
              className={({ isActive }) =>
                'px-2.5 py-1.5 rounded-lg text-sm font-medium transition-colors ' +
                (isActive
                  ? 'bg-violet-100 text-violet-700'
                  : 'text-violet-500 hover:bg-violet-50 hover:text-violet-700')
              }
            >
              🏷️
            </NavLink>
          </div>
        )}

        {/* ── Compte utilisateur ─────────────────────────────────────── */}
        {user ? (
          <div className="flex items-center gap-2 ml-2 pl-2 border-l border-stone-200">
            <span className="text-xs text-stone-500 max-w-[120px] truncate hidden sm:inline" title={user.email}>
              {user.email}
            </span>
            <button
              onClick={handleLogout}
              title="Se déconnecter"
              className="px-2.5 py-1.5 rounded-lg text-sm text-stone-500 hover:bg-stone-100 hover:text-stone-700 transition-colors"
            >
              ↪
            </button>
          </div>
        ) : (
          <NavLink
            to="/connexion"
            className="ml-2 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition-colors"
          >
            Connexion
          </NavLink>
        )}

      </div>
    </nav>
  )
}
