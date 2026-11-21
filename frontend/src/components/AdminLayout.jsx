import { useEffect, useState } from 'react'
import { NavLink, Navigate, Outlet, useLocation } from 'react-router-dom'
import { getAdminStats } from '../api'
import { useAuth } from '../context/AuthContext'

const GROUPS = [
  {
    label: 'Suivi',
    items: [
      { to: '/admin', label: 'Vue d’ensemble', end: true },
      { to: '/admin/catalogue', label: 'Catalogue' },
      { to: '/admin/explorer', label: 'Explorer' },
      { to: '/admin/taches', label: 'Tâches' },
    ],
  },
  {
    label: 'Communauté et réglages',
    items: [
      { to: '/admin/membres', label: 'Membres' },
      { to: '/admin/communaute', label: 'Communauté' },
      { to: '/admin/mappings', label: 'Mappings', badgeKey: 'unmapped' },
    ],
  },
]

// Anciennes adresses : /admin?onglet=taches, /admin/mecaniques, /admin/themes.
const LEGACY_TABS = {
  catalogue: '/admin/catalogue', taches: '/admin/taches', explorer: '/admin/explorer',
  membres: '/admin/membres', communaute: '/admin/communaute', mappings: '/admin/mappings',
}

function SectionLink({ to, label, end, badge }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        'flex min-h-11 shrink-0 items-center justify-between gap-3 rounded-xl border-[3px] px-3.5 font-bold transition-colors ' +
        (isActive ? 'border-ink bg-ink text-paper' : 'border-transparent hover:border-ink hover:bg-brand-yellow')
      }
    >
      {label}
      {badge > 0 && (
        <span className="rounded-full bg-brand-red px-2 text-xs font-bold leading-5 text-ink" title={`${badge} à traiter`}>
          <span className="sr-only">{badge} à traiter</span>
          <span aria-hidden="true">{badge}</span>
        </span>
      )}
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

  // Éléments à traiter par entrée, tirés des chiffres déjà chargés.
  const badges = { unmapped: stats ? Number(stats.mappings.unmapped_bgg_mechanics) + Number(stats.themes.unmapped_bgg_categories) : 0 }

  return (
    // Fond papier sur toute la largeur : l'administration se lit sur blanc, pas sur le plateau bleu.
    <div className="min-h-dvh bg-paper">
      <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row lg:items-start lg:gap-8 lg:px-10 lg:py-8">
        <nav aria-label="Administration" className="lg:sticky lg:top-6 lg:w-60 lg:shrink-0 lg:rounded-2xl lg:border-[3px] lg:border-ink lg:bg-cream lg:p-3 lg:shadow-token-sm">
          <p className="mb-2 hidden px-1.5 pt-1 font-display text-2xl lg:block">Administration</p>
          <div className="-mx-1 flex gap-1.5 overflow-x-auto overflow-y-hidden px-1 py-1 lg:mx-0 lg:flex-col lg:gap-4 lg:overflow-visible lg:p-0">
            {GROUPS.map(({ label, items }) => (
              <div key={label} className="flex gap-1.5 lg:flex-col lg:gap-1">
                <p className="hidden px-1.5 text-xs font-bold uppercase text-stone-500 lg:block">{label}</p>
                {items.map(({ badgeKey, ...item }) => <SectionLink key={item.to} {...item} badge={badgeKey ? badges[badgeKey] : 0} />)}
              </div>
            ))}
          </div>
        </nav>
        <div className="flex min-w-0 flex-1 flex-col gap-6">
          <Outlet context={{ stats, error }} />
        </div>
      </div>
    </div>
  )
}
