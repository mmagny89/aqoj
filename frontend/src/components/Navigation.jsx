import { NavLink, useNavigate } from 'react-router-dom'
import { useState } from 'react'

export default function Navigation() {
  const [query, setQuery] = useState('')
  const navigate = useNavigate()

  const handleSearch = (e) => {
    e.preventDefault()
    if (query.trim()) {
      navigate(`/rechercher?q=${encodeURIComponent(query.trim())}`)
      setQuery('')
    }
  }

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-b border-stone-200 h-16 flex items-center px-4 gap-3">
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
        {[
          { to: '/recommander', label: '✨', title: 'Ce soir' },
          { to: '/ludotheque', label: '📚', title: 'Ludothèque' },
          { to: '/parties', label: '📊', title: 'Parties' },
          { to: '/importer', label: '⬇', title: 'BGG' },
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
      </div>
    </nav>
  )
}
