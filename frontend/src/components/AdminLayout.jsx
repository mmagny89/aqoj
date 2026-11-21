import { useEffect, useState } from 'react'
import { NavLink, Navigate, Outlet, useLocation } from 'react-router-dom'
import { getAdminStats } from '../api'
import { useAuth } from '../context/AuthContext'

const SECTIONS = [
  { to: '/admin', label: 'Vue d’ensemble', end: true },
  { to: '/admin/catalogue', label: 'Catalogue' },
  { to: '/admin/explorer', label: 'Explorer' },
  { to: '/admin/taches', label: 'Tâches' },
  { to: '/admin/membres', label: 'Membres' },
  { to: '/admin/communaute', label: 'Communauté' },
  { to: '/admin/mappings', label: 'Mappings' },
]

// Anciennes adresses : /admin?onglet=taches, /admin/mecaniques, /admin/themes.
const LEGACY_TABS = {
  catalogue: '/admin/catalogue', taches: '/admin/taches', explorer: '/admin/explorer',
  membres: '/admin/membres', communaute: '/admin/communaute', mappings: '/admin/mappings',
}

function SectionLink({ to, label, end }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        'inline-flex min-h-11 shrink-0 items-center rounded-xl border-[3px] px-3.5 font-bold transition-colors ' +
        (isActive ? 'border-ink bg-brand-yellow shadow-token-sm' : 'border-transparent text-stone-700 hover:bg-amber-200')
      }
    >
      {label}
    </NavLink>
  )
}

/**
 * Coque de l'administration : un sous-menu (colonne au bureau, bande défilante sur téléphone) et la page choisie.
 * Les chiffres (`getAdminStats`) sont chargés une fois pour toutes les pages qui en ont besoin.
 */
export default function AdminLayout() {
  const { user } = useAuth()
  const { pathname, search } = useLocation()
  const [stats, setStats] = useState(null)
  const [error, setError] = useState(null)

  const isAdmin = Boolean(user?.isAdmin)
  useEffect(() => {
    if (!isAdmin) return
    getAdminStats().then(setStats).catch(e => setError(e.message))
  }, [isAdmin])

  if (!isAdmin) return <Navigate to="/" replace />

  const legacyTab = pathname === '/admin' ? new URLSearchParams(search).get('onglet') : null
  if (legacyTab && LEGACY_TABS[legacyTab]) return <Navigate to={LEGACY_TABS[legacyTab]} replace />

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row lg:items-start lg:gap-8 lg:px-10 lg:py-8">
      <nav aria-label="Administration" className="lg:sticky lg:top-6 lg:w-52 lg:shrink-0">
        <p className="mb-2 hidden text-xs font-bold uppercase text-stone-500 lg:block">Administration</p>
        <div className="-mx-1 flex gap-1.5 overflow-x-auto overflow-y-hidden px-1 py-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:p-0">
          {SECTIONS.map(section => <SectionLink key={section.to} {...section} />)}
        </div>
      </nav>
      <div className="flex min-w-0 flex-1 flex-col gap-6">
        <Outlet context={{ stats, error }} />
      </div>
    </div>
  )
}
