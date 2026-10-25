import { useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Footer from './Footer'
import {
  DiceIcon, DownloadIcon, HelpIcon, HomeIcon, LogoutIcon, SearchIcon, ShelfIcon, SparkIcon, ToolIcon, UserIcon,
} from './icons'

/* Liens du rail (bureau). Le « Ce soir on joue » est la page de recommandation. */
const MAIN_LINKS = [
  { to: '/', label: 'Accueil', Icon: HomeIcon, end: true },
  { to: '/recommander', label: 'Ce soir on joue', Icon: SparkIcon },
  { to: '/rechercher', label: 'Explorer', Icon: SearchIcon },
  { to: '/ludotheque', label: 'Ma ludothèque', Icon: ShelfIcon },
  { to: '/parties', label: 'Mes parties', Icon: DiceIcon },
  { to: '/importer', label: 'Import BGG', Icon: DownloadIcon },
]

function Brand({ compact = false }) {
  return (
    <Link to="/" aria-label="Envie de Jouer, accueil" className="flex items-center gap-3 min-w-0">
      <img src="/edj-mascotte.png" alt="" width="60" height="60" className={compact ? 'h-11 w-11 object-contain' : 'h-[60px] w-[60px] object-contain'} />
      {/* Sur téléphone, la mascotte seule : la place va à la recherche. */}
      <span className={compact ? 'hidden min-w-0 sm:block' : 'block min-w-0'}>
        <strong className="block font-display text-[1.65rem] leading-none whitespace-nowrap">Envie de Jouer</strong>
        <span className="text-xs font-bold uppercase text-stone-500">À quoi on joue</span>
      </span>
    </Link>
  )
}

function RailLink({ to, label, Icon, end }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        'flex min-h-12 items-center gap-3 rounded-xl border-[3px] px-3.5 font-bold transition-colors ' +
        (isActive
          ? 'border-ink bg-brand-yellow shadow-token-sm'
          : 'border-transparent text-stone-700 hover:bg-amber-200')
      }
    >
      <Icon />
      {label}
    </NavLink>
  )
}

function Account({ user, onLogout }) {
  if (!user) {
    return <Link to="/connexion" className="btn btn-secondary w-full">Connexion</Link>
  }
  const initial = (user.email ?? '?').charAt(0).toUpperCase()
  return (
    <div className="rounded-xl border-[3px] border-ink bg-cream p-3">
      <Link to="/compte" className="flex min-h-11 items-center gap-3">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full border-[3px] border-ink bg-brand-red font-display text-xl" aria-hidden="true">{initial}</span>
        <span className="block min-w-0">
          <strong className="block truncate">{user.email}</strong>
          <span className="flex flex-wrap gap-1">
            <span className={`badge ${user.role === 'vip' ? 'bg-brand-yellow' : 'bg-paper'}`}>{user.role === 'vip' ? 'VIP' : 'Membre'}</span>
            {user.isAdmin && <span className="badge bg-brand-red">Admin</span>}
          </span>
        </span>
      </Link>
      <button type="button" onClick={onLogout} className="mt-2 flex min-h-11 w-full items-center gap-2 rounded-lg px-2 text-sm font-bold text-stone-600 hover:bg-amber-200">
        <LogoutIcon width={20} height={20} /> Se déconnecter
      </button>
    </div>
  )
}

function SideRail({ user, onLogout }) {
  return (
    <aside className="sticky top-0 hidden h-dvh w-68 shrink-0 flex-col gap-6 overflow-y-auto border-r-[3px] border-ink/15 bg-paper px-5 py-6 lg:flex">
      <Brand />
      <nav aria-label="Navigation principale" className="flex flex-col gap-1.5">
        {MAIN_LINKS.map(link => <RailLink key={link.to} {...link} />)}
      </nav>
      <div className="mt-auto flex flex-col gap-3">
        <RailLink to="/aide" label="Aide" Icon={HelpIcon} />
        {user?.isAdmin && <RailLink to="/admin" label="Administration" Icon={ToolIcon} />}
        <Account user={user} onLogout={onLogout} />
      </div>
    </aside>
  )
}

function TopBar() {
  const [query, setQuery] = useState('')
  const navigate = useNavigate()

  const submit = (event) => {
    event.preventDefault()
    if (query.trim()) {
      navigate(`/rechercher?q=${encodeURIComponent(query.trim())}`)
      setQuery('')
    }
  }

  return (
    <header className="sticky top-0 z-30 flex items-center gap-3 border-b-[3px] border-ink/15 bg-paper/95 px-4 py-2.5 backdrop-blur lg:bg-transparent lg:px-10 lg:pt-7 lg:pb-0 lg:backdrop-blur-none lg:border-b-0">
      <div className="lg:hidden"><Brand compact /></div>
      <form onSubmit={submit} role="search" className="ml-auto flex min-h-12 min-w-0 max-w-2xl flex-1 items-center gap-3 rounded-2xl border-[3px] border-ink bg-paper px-4 shadow-token-sm lg:ml-0 lg:min-h-14">
        <SearchIcon className="shrink-0 text-stone-500" width={22} height={22} />
        <label htmlFor="recherche-globale" className="sr-only">Chercher un jeu</label>
        <input
          id="recherche-globale"
          type="search"
          value={query}
          onChange={event => setQuery(event.target.value)}
          placeholder="Chercher un jeu, en français ou en anglais"
          className="min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-stone-400"
        />
      </form>
    </header>
  )
}

/* Barre d'onglets du téléphone : les cinq gestes de tous les jours, à portée de pouce. */
function TabBar({ user }) {
  const tabs = [
    { to: '/', label: 'Accueil', Icon: HomeIcon, end: true },
    { to: '/recommander', label: 'Ce soir', Icon: SparkIcon },
    { to: '/ludotheque', label: 'Ludothèque', Icon: ShelfIcon },
    { to: '/parties', label: 'Parties', Icon: DiceIcon },
    user
      ? { to: '/compte', label: 'Compte', Icon: UserIcon }
      : { to: '/connexion', label: 'Connexion', Icon: UserIcon },
  ]
  return (
    <nav aria-label="Navigation principale" className="fixed inset-x-0 bottom-0 z-40 flex border-t-[3px] border-ink bg-paper pb-[env(safe-area-inset-bottom)] lg:hidden">
      {tabs.map(({ to, label, Icon, end }) => (
        <NavLink
          key={to}
          to={to}
          end={end}
          className={({ isActive }) =>
            'flex min-h-16 flex-1 flex-col items-center justify-center gap-0.5 text-xs font-bold ' +
            (isActive ? 'bg-brand-yellow text-ink' : 'text-stone-700')
          }
        >
          <Icon />
          {label}
        </NavLink>
      ))}
    </nav>
  )
}

export default function AppShell({ children }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/')
  }

  return (
    <div className="min-h-dvh lg:flex">
      <a href="#contenu" className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-brand-yellow focus:px-4 focus:py-2 focus:font-bold">
        Aller au contenu
      </a>
      <SideRail user={user} onLogout={handleLogout} />
      <div className="flex min-w-0 flex-1 flex-col">
        <TopBar />
        <main id="contenu" className="flex-1 pb-24 lg:pb-0">{children}</main>
        <Footer />
      </div>
      <TabBar user={user} />
    </div>
  )
}
