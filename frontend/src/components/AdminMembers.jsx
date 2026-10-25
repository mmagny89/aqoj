import { useEffect, useState } from 'react'
import { createInvitation, getAdminUsers, getInvitations, revokeInvitation, updateUserRole } from '../api'
import { useAuth } from '../context/AuthContext'
import { ErrorMessage, Field, SelectField } from './forms'

const ROLES = { member: 'Membre', vip: 'VIP' }

const STATUS = {
  active: { label: 'Actif', style: 'bg-brand-green text-paper' },
  exhausted: { label: 'Épuisé', style: 'bg-cream' },
  expired: { label: 'Expiré', style: 'bg-cream' },
  revoked: { label: 'Révoqué', style: 'bg-amber-100' },
}

function fmtDate(iso) {
  return iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
}

const linkButton = 'inline-flex min-h-11 items-center px-2 font-bold underline underline-offset-4'

/* ── Codes d'invitation ──────────────────────────────────────────────── */
function Invitations() {
  const [codes, setCodes] = useState(null)
  const [form, setForm] = useState({ label: '', role: 'member', maxUses: 1, expiresAt: '' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [copied, setCopied] = useState(null)

  useEffect(() => {
    getInvitations().then(setCodes).catch(e => setError(e.message))
  }, [])

  const submit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const created = await createInvitation({
        label: form.label,
        role: form.role,
        maxUses: Number(form.maxUses),
        // Le code reste valable toute la journée choisie.
        ...(form.expiresAt ? { expiresAt: `${form.expiresAt}T23:59:59` } : {}),
      })
      setCodes(prev => [created, ...(prev ?? [])])
      setForm({ label: '', role: 'member', maxUses: 1, expiresAt: '' })
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  const revoke = async (id) => {
    if (!confirm('Révoquer ce code ? Il ne servira plus.')) return
    try {
      const updated = await revokeInvitation(id)
      setCodes(prev => prev.map(c => (c.id === id ? updated : c)))
    } catch (e) {
      setError(e.message)
    }
  }

  const copy = async (code) => {
    try {
      await navigator.clipboard.writeText(code)
      setCopied(code)
    } catch {
      setCopied(null)
    }
  }

  return (
    <section aria-labelledby="titre-codes" className="flex flex-col gap-4">
      <h3 id="titre-codes" className="text-2xl">Codes d'invitation</h3>

      <form onSubmit={submit} className="panel grid grid-cols-1 gap-4 bg-amber-100 p-5 sm:grid-cols-2">
        <Field className="sm:col-span-2" label="Libellé" value={form.label} required maxLength={100}
          onChange={e => setForm(f => ({ ...f, label: e.target.value }))} placeholder="Soirée du 12 octobre, Pour Camille…" />
        <SelectField label="Rôle accordé" value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))}>
          {Object.entries(ROLES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </SelectField>
        <Field label="Nombre d'usages" type="number" min={1} max={1000} required value={form.maxUses}
          onChange={e => setForm(f => ({ ...f, maxUses: e.target.value }))} />
        <Field label="Valable jusqu'au" hint="(facultatif)" type="date" value={form.expiresAt}
          onChange={e => setForm(f => ({ ...f, expiresAt: e.target.value }))} />
        <div className="flex items-end">
          <button type="submit" disabled={busy || !form.label.trim()} className="btn btn-secondary w-full sm:w-auto">
            {busy ? 'Création…' : 'Créer le code'}
          </button>
        </div>
      </form>

      <ErrorMessage>{error}</ErrorMessage>

      {codes === null ? (
        <p role="status" className="text-stone-600">Chargement…</p>
      ) : codes.length === 0 ? (
        <p className="text-stone-600">Aucun code pour l'instant.</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-3 p-0">
          {codes.map(c => {
            const status = STATUS[c.status] ?? STATUS.expired
            return (
              <li key={c.id} className="panel flex flex-wrap items-center justify-between gap-x-4 gap-y-2 p-4">
                <div className="min-w-0">
                  <p className="font-bold">{c.label}</p>
                  <p className="font-mono text-xl tracking-wider">{c.code}</p>
                  <p className="text-sm text-stone-600">
                    {ROLES[c.role]} · {c.usedCount} / {c.maxUses} usage{c.maxUses > 1 ? 's' : ''} · jusqu'au {fmtDate(c.expiresAt)}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                  <span className={`badge ${status.style}`}>{status.label}</span>
                  <button type="button" onClick={() => copy(c.code)} className={`${linkButton} text-brand-blue`}>
                    {copied === c.code ? 'Copié' : 'Copier'}
                  </button>
                  {c.status === 'active' && (
                    <button type="button" onClick={() => revoke(c.id)} className={`${linkButton} text-amber-600`}>Révoquer</button>
                  )}
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}

/* ── Comptes et rôles ────────────────────────────────────────────────── */
function Accounts() {
  const { user: me } = useAuth()
  const [filter, setFilter] = useState('')
  const [term, setTerm] = useState('')
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    getAdminUsers({ role: filter, q: query, page }).then(setData).catch(e => setError(e.message))
  }, [filter, query, page])

  const choose = (value) => { setFilter(value); setPage(1) }
  const search = (event) => { event.preventDefault(); setQuery(term.trim()); setPage(1) }

  const changeRole = async (account, role) => {
    setError(null)
    try {
      const updated = await updateUserRole(account.id, role)
      setData(prev => ({ ...prev, items: prev.items.map(a => (a.id === account.id ? { ...a, role: updated.role } : a)) }))
    } catch (e) {
      setError(e.message)
    }
  }

  const pages = data ? Math.max(1, Math.ceil(data.total / data.perPage)) : 1

  return (
    <section aria-labelledby="titre-comptes" className="flex flex-col gap-4">
      <h3 id="titre-comptes" className="text-2xl">Comptes</h3>

      <div role="group" aria-label="Filtrer par rôle" className="flex flex-wrap gap-2">
        {[['', 'Tous'], ['member', 'Membres'], ['vip', 'VIP'], ['admin', 'Admins']].map(([value, label]) => (
          <button key={value} type="button" className="chip" aria-pressed={filter === value} onClick={() => choose(value)}>{label}</button>
        ))}
      </div>

      <form onSubmit={search} role="search" className="flex gap-2">
        <label htmlFor="comptes-q" className="sr-only">Adresse e-mail</label>
        <input id="comptes-q" value={term} onChange={e => setTerm(e.target.value)} placeholder="Adresse e-mail"
          className="min-h-12 min-w-0 flex-1 rounded-xl border-[3px] border-ink bg-paper px-4" />
        <button type="submit" className="btn btn-secondary">Chercher</button>
      </form>

      <ErrorMessage>{error}</ErrorMessage>

      {!data ? (
        <p role="status" className="text-stone-600">Chargement…</p>
      ) : (
        <>
          <p className="text-stone-600" aria-live="polite">{data.total.toLocaleString('fr')} compte{data.total > 1 ? 's' : ''}</p>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {data.items.map(a => (
              <li key={a.id} className="panel flex flex-wrap items-center justify-between gap-x-4 gap-y-2 p-4">
                <div className="min-w-0">
                  <p className="break-all font-bold">{a.email}</p>
                  <p className="text-sm text-stone-600">
                    Inscrit le {fmtDate(a.createdAt)}{a.invitationLabel ? ` · code « ${a.invitationLabel} »` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {a.isAdmin && <span className="badge bg-brand-yellow">Admin</span>}
                  {a.id === me?.id ? (
                    <span className="badge bg-cream">{ROLES[a.role]} (vous)</span>
                  ) : (
                    <select
                      aria-label={`Rôle de ${a.email}`}
                      value={a.role}
                      onChange={e => changeRole(a, e.target.value)}
                      className="min-h-11 rounded-xl border-[3px] border-ink bg-paper px-3 font-bold"
                    >
                      {Object.entries(ROLES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                  )}
                </div>
              </li>
            ))}
          </ul>
          <nav aria-label="Pagination" className="flex items-center justify-between gap-3">
            <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)} className="btn bg-paper">Précédent</button>
            <span className="text-stone-600">Page {page} sur {pages}</span>
            <button type="button" disabled={page >= pages} onClick={() => setPage(page + 1)} className="btn bg-paper">Suivant</button>
          </nav>
        </>
      )}
    </section>
  )
}

/** Onglet « Membres » du tableau de bord : qui peut s'inscrire, et ce que chaque compte est. */
export default function AdminMembers() {
  return (
    <div className="flex flex-col gap-8">
      <Invitations />
      <Accounts />
    </div>
  )
}
