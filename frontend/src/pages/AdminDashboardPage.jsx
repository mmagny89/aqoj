import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getAdminStats } from '../api'
import { useAuth } from '../context/AuthContext'

/* ── Carte de stat simple ────────────────────────────────────────────── */
function StatCard({ label, value, sub, color = 'stone', icon }) {
  const colors = {
    amber:  'bg-amber-50  border-amber-200  text-amber-700',
    green:  'bg-green-50  border-green-200  text-green-700',
    red:    'bg-red-50    border-red-200    text-red-700',
    blue:   'bg-blue-50   border-blue-200   text-blue-700',
    violet: 'bg-violet-50 border-violet-200 text-violet-700',
    stone:  'bg-stone-50  border-stone-200  text-stone-600',
    orange: 'bg-orange-50 border-orange-200 text-orange-700',
  }
  return (
    <div className={`rounded-xl border p-4 ${colors[color]}`}>
      <div className="flex items-start justify-between gap-2 mb-1">
        <p className="text-xs font-semibold uppercase tracking-wide opacity-70 leading-tight">{label}</p>
        {icon && <span className="text-lg leading-none flex-shrink-0">{icon}</span>}
      </div>
      <p className="text-2xl font-black">{value ?? '—'}</p>
      {sub && <p className="text-xs mt-0.5 opacity-60">{sub}</p>}
    </div>
  )
}

/* ── Barre de progression ────────────────────────────────────────────── */
function ProgressBar({ value, max, color = 'amber', label }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0
  const bars = { amber: 'bg-amber-400', green: 'bg-green-400', red: 'bg-red-400', blue: 'bg-blue-400', violet: 'bg-violet-400' }
  return (
    <div>
      <div className="flex justify-between text-xs text-stone-500 mb-1">
        <span>{label}</span>
        <span className="font-semibold tabular-nums">{value.toLocaleString('fr')} / {max.toLocaleString('fr')} ({pct}%)</span>
      </div>
      <div className="h-2 bg-stone-200 rounded-full overflow-hidden">
        <div className={`h-full rounded-full transition-all ${bars[color]}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

/* ── Section ─────────────────────────────────────────────────────────── */
function Section({ title, icon, children }) {
  return (
    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-5">
      <h2 className="text-sm font-bold text-stone-500 uppercase tracking-wide mb-4 flex items-center gap-2">
        <span>{icon}</span> {title}
      </h2>
      {children}
    </div>
  )
}

/* ── Formatage date ──────────────────────────────────────────────────── */
function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function fmtNum(n) {
  return n != null ? Number(n).toLocaleString('fr') : '—'
}

/* ── Page principale ─────────────────────────────────────────────────── */
export default function AdminDashboardPage() {
  const { user }         = useAuth()
  const navigate         = useNavigate()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  useEffect(() => {
    if (!user?.isAdmin) { navigate('/'); return }
    getAdminStats()
      .then(setStats)
      .catch(e => setError(e.message))
      .finally(() => setLoading(false))
  }, [user, navigate])

  if (!user?.isAdmin) return null

  if (loading) return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
    </div>
  )

  if (error) return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">{error}</div>
    </div>
  )

  const { games, users, sessions, mappings } = stats
  const g = games
  const u = users
  const col = users.collections
  const s = sessions
  const m = mappings

  return (
    <div className="max-w-5xl mx-auto px-4 py-8 space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-black text-stone-900">Tableau de bord</h1>
          <p className="text-stone-400 text-sm mt-0.5">
            Mis à jour le {fmtDate(stats.generated_at)}
          </p>
        </div>
        <Link
          to="/admin/mecaniques"
          className="px-4 py-2 bg-stone-800 text-white text-sm font-semibold rounded-xl hover:bg-stone-700 transition-colors"
        >
          ⚙️ Mappings mécaniques
        </Link>
      </div>

      {/* ── Jeux ─────────────────────────────────────────────────────── */}
      <Section title="Catalogue de jeux" icon="🎲">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
          <StatCard label="Jeux de base"  value={fmtNum(g.base_games)}  icon="🎲" color="amber" />
          <StatCard label="Extensions"    value={fmtNum(g.expansions)}  icon="🧩" color="blue" />
          <StatCard label="Total catalogue" value={fmtNum(g.total)}     icon="📦" color="stone" />
          <StatCard label="À resyncer"    value={fmtNum(g.stale_count)} icon="🔄" color={g.stale_count > 500 ? 'orange' : 'green'}
            sub={g.stale_count > 0 ? 'last_synced_at > 30 j' : 'Tout est à jour'} />
        </div>

        <div className="space-y-3 mb-5">
          <ProgressBar
            label="Enrichis (source = bgg_api)"
            value={Number(g.enriched)} max={Number(g.total)}
            color="green"
          />
          <ProgressBar
            label="Avec mécaniques BGG mappées"
            value={Number(g.total) - Number(g.no_mechanics)} max={Number(g.total)}
            color="amber"
          />
          <ProgressBar
            label="Avec image"
            value={Number(g.total) - Number(g.no_image)} max={Number(g.total)}
            color="blue"
          />
          <ProgressBar
            label="Avec description"
            value={Number(g.total) - Number(g.no_description)} max={Number(g.total)}
            color="violet"
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-stone-500">
          <div className="bg-stone-50 rounded-xl p-3 border border-stone-200">
            <p className="font-semibold text-stone-700 mb-1">⏱ Dernier enrichissement</p>
            <p>{fmtDate(g.last_synced)}</p>
          </div>
          <div className="bg-stone-50 rounded-xl p-3 border border-stone-200">
            <p className="font-semibold text-stone-700 mb-1">🕰 Enrichissement le plus ancien</p>
            <p>{fmtDate(g.oldest_synced)}</p>
          </div>
          <div className="bg-stone-50 rounded-xl p-3 border border-stone-200">
            <p className="font-semibold text-stone-700 mb-1">⚠️ Données manquantes</p>
            <p>{fmtNum(g.no_mechanics)} sans mécaniques · {fmtNum(g.no_image)} sans image · {fmtNum(g.no_description)} sans description</p>
          </div>
        </div>
      </Section>

      {/* ── Utilisateurs ─────────────────────────────────────────────── */}
      <Section title="Utilisateurs" icon="👥">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
          <StatCard label="Total"              value={fmtNum(u.total)}    icon="👤" color="amber" />
          <StatCard label="Avec collection BGG" value={fmtNum(u.with_bgg)} icon="📚" color="green"
            sub={u.total > 0 ? `${Math.round(u.with_bgg / u.total * 100)}% des comptes` : null} />
          <StatCard label="Sans collection BGG" value={fmtNum(u.without_bgg)} icon="📭" color="stone" />
          <StatCard label="Admins"             value={fmtNum(u.admins)}   icon="🔑" color="violet" />
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <StatCard label="Liens jeu ↔ utilisateur" value={fmtNum(col.total_links)}    icon="🔗" color="blue" />
          <StatCard label="Jeux notés (BGG)"         value={fmtNum(col.rated_links)}   icon="⭐" color="amber"
            sub={col.total_links > 0 ? `${Math.round(col.rated_links / col.total_links * 100)}% de la collection` : null} />
          <StatCard label="Moy. jeux / utilisateur"  value={col.avg_per_user ?? '—'}   icon="📊" color="stone" />
          <StatCard label="Max jeux (1 utilisateur)" value={fmtNum(col.max_per_user)}  icon="🏆" color="stone" />
        </div>
      </Section>

      {/* ── Parties ──────────────────────────────────────────────────── */}
      <Section title="Parties enregistrées" icon="🎮">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
          <StatCard label="Total parties"    value={fmtNum(s.total)}       icon="🎮" color="amber" />
          <StatCard label="Ce mois-ci"       value={fmtNum(s.this_month)}  icon="📅" color="green" />
          <StatCard label="Cette semaine"    value={fmtNum(s.this_week)}   icon="📆" color="blue" />
          <StatCard label="Cette année"      value={fmtNum(s.this_year)}   icon="🗓" color="stone" />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
          <StatCard label="Jeux différents joués"      value={fmtNum(s.distinct_games)}  icon="🎲" color="violet" />
          <StatCard label="Joueurs actifs (sessions)"  value={fmtNum(s.distinct_users)}  icon="👥" color="stone" />
          <StatCard label="Avec une note"              value={fmtNum(s.with_rating)}     icon="⭐" color="amber"
            sub={s.total > 0 ? `${Math.round(s.with_rating / s.total * 100)}% des parties` : null} />
        </div>

        <div className="bg-stone-50 rounded-xl p-3 border border-stone-200 text-xs text-stone-500">
          <span className="font-semibold text-stone-700">Dernière partie enregistrée : </span>
          {fmtDate(s.last_session)}
        </div>
      </Section>

      {/* ── Mappings mécaniques ───────────────────────────────────────── */}
      <Section title="Mappings mécaniques" icon="⚙️">
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <StatCard label="Mappings actifs"         value={fmtNum(m.total_mappings)}         icon="✅" color="green" />
          <StatCard label="Familles utilisées"       value={fmtNum(m.families_used)}          icon="🏷" color="blue" />
          <StatCard label="Mécaniques BGG non mappées" value={fmtNum(m.unmapped_bgg_mechanics)} icon="⚠️"
            color={m.unmapped_bgg_mechanics > 0 ? 'orange' : 'green'}
            sub={m.unmapped_bgg_mechanics > 0 ? 'Visibles dans l\'onglet "Non mappées"' : 'Tout est mappé 🎉'} />
        </div>
        <div className="mt-3">
          <Link to="/admin/mecaniques?tab=unmapped"
            className="text-sm text-amber-600 hover:underline font-medium">
            Gérer les mappings →
          </Link>
        </div>
      </Section>

    </div>
  )
}
