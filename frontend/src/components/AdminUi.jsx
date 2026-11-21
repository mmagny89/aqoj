import { Link } from 'react-router-dom'

/* Éléments communs de l'administration, au style « plateau » : bord d'encre, papier, ombre dure, aucun emoji. */

export function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function fmtNum(n) {
  return n != null ? Number(n).toLocaleString('fr') : '—'
}

export const pct = (part, whole) => (Number(whole) > 0 ? `${Math.round(Number(part) / Number(whole) * 100)} %` : null)

/** En-tête d'une page d'administration : titre, phrase d'explication, actions à droite. */
export function PageHeader({ title, intro, children }) {
  return (
    <header className="flex flex-wrap items-end justify-between gap-3">
      <div className="min-w-0">
        <h1 className="text-3xl leading-tight sm:text-4xl">{title}</h1>
        {intro && <p className="mt-1 text-stone-600">{intro}</p>}
      </div>
      {children && <div className="flex flex-wrap gap-3">{children}</div>}
    </header>
  )
}

/** Bloc titré. `id` relie le titre à la région pour les lecteurs d'écran. */
export function Section({ id, title, intro, children }) {
  return (
    <section aria-labelledby={id} className="panel flex flex-col gap-4 p-5">
      <div>
        <h2 id={id} className="text-xl sm:text-2xl">{title}</h2>
        {intro && <p className="mt-0.5 text-sm text-stone-600">{intro}</p>}
      </div>
      {children}
    </section>
  )
}

/** Chiffre clé. `warn` le met en jaune ; `to` ou `onClick` en font un lien vers la liste concernée. */
export function StatCard({ label, value, sub, warn = false, to, onClick }) {
  const base = `flex min-h-11 flex-col gap-0.5 rounded-xl border-[3px] border-ink p-3.5 text-left ${warn ? 'bg-brand-yellow' : 'bg-paper'}`
  const content = (
    <>
      <span className="text-xs font-bold uppercase text-stone-700">{label}</span>
      <strong className="font-display text-3xl leading-none">{value ?? '—'}</strong>
      {sub && <span className="text-sm text-stone-700">{sub}</span>}
    </>
  )
  const hover = ' transition-shadow hover:shadow-token-sm'
  if (to) return <Link to={to} className={base + hover}>{content}</Link>
  if (onClick) return <button type="button" onClick={onClick} className={base + hover}>{content}</button>
  return <div className={base}>{content}</div>
}

export function StatGrid({ children, columns = 4 }) {
  const cols = { 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' }[columns]
  return <div className={`m-0 grid grid-cols-2 gap-3 ${cols}`}>{children}</div>
}

/** Barre de progression. Jamais 100 % tant qu'il manque un élément : l'arrondi cacherait le reste à faire. */
export function ProgressBar({ value, max, label, hint, to }) {
  const done = max > 0 ? (value >= max ? 100 : Math.min(99, Math.round((value / max) * 100))) : 0
  return (
    <div>
      <div className="mb-1 flex flex-wrap justify-between gap-x-3 text-sm">
        <span className="font-bold">{label}</span>
        <span className="tabular-nums text-stone-700">{fmtNum(value)} / {fmtNum(max)} ({done} %)</span>
      </div>
      <div role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={100} aria-valuenow={done}
        className="h-3 overflow-hidden rounded-full border-2 border-ink bg-paper">
        <div className="h-full bg-brand-green" style={{ width: `${done}%` }} />
      </div>
      {hint && value < max && (
        <p className="mt-1 text-sm text-stone-600">
          {hint}
          {to && <> <Link to={to} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">Voir la liste</Link></>}
        </p>
      )}
    </div>
  )
}

/** Ligne « à traiter » : un constat chiffré et le lien qui mène à sa liste. */
export function Notice({ children, to, cta = 'Voir la liste' }) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl border-[3px] border-ink border-l-[10px] border-l-brand-yellow bg-cream px-4 py-2.5">
      <span>{children}</span>
      {to && <Link to={to} className="inline-flex min-h-11 items-center font-bold underline underline-offset-4">{cta}</Link>}
    </li>
  )
}

export function Spinner() {
  return (
    <p role="status" className="flex min-h-48 items-center justify-center gap-3 text-stone-600">
      <span className="h-5 w-5 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
      Chargement…
    </p>
  )
}
