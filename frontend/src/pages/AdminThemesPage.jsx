import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import {
  getThemeMappings,
  getUnmappedBggCategories,
  createThemeMapping,
  updateThemeMapping,
  deleteThemeMapping,
} from '../api'
import { GROUP_META, GROUP_ORDER, invalidateThemeCache } from '../hooks/useThemeMappings'
import { ErrorMessage, Field, SelectField } from '../components/forms'

/* ── Fenêtre d'ajout / édition ───────────────────────────────────────── */
function MappingModal({ initial, onSave, onClose }) {
  const [bggCategory, setBggCategory] = useState(initial?.bggCategory ?? '')
  const [themeGroup,  setThemeGroup]  = useState(initial?.themeGroup  ?? 'universe')
  const [themeLabel,  setThemeLabel]  = useState(initial?.themeLabel  ?? '')
  const [themeEmoji,  setThemeEmoji]  = useState(initial?.themeEmoji  ?? '🏷️')
  const [saving, setSaving] = useState(false)
  const [error,  setError]  = useState(null)

  // Échap referme la fenêtre, comme un clic à l'extérieur.
  useEffect(() => {
    const onKey = (event) => { if (event.key === 'Escape') onClose() }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  const handleSave = async (e) => {
    e.preventDefault()
    if (!bggCategory.trim() || !themeLabel.trim()) return
    setSaving(true)
    setError(null)
    try {
      await onSave({ bggCategory: bggCategory.trim(), themeGroup, themeLabel: themeLabel.trim(), themeEmoji: themeEmoji.trim() || '🏷️' })
      onClose()
    } catch (err) {
      setError(err.message)
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" onClick={onClose}>
      <form
        role="dialog"
        aria-modal="true"
        aria-labelledby="titre-modal"
        onSubmit={handleSave}
        onClick={e => e.stopPropagation()}
        className="panel flex max-h-full w-full max-w-md flex-col gap-4 overflow-y-auto p-6 shadow-token-lg"
      >
        <h2 id="titre-modal" className="text-3xl">{initial && !initial._new ? 'Modifier le mapping' : 'Ajouter un mapping'}</h2>

        <Field
          label="Catégorie BGG"
          value={bggCategory}
          onChange={e => setBggCategory(e.target.value)}
          placeholder="Ex : Science Fiction"
          disabled={!!initial}
          autoFocus={!initial}
        />

        <SelectField label="Groupe" value={themeGroup} onChange={e => setThemeGroup(e.target.value)}>
          {GROUP_ORDER.map(key => (
            <option key={key} value={key}>{GROUP_META[key].emoji} {GROUP_META[key].label}</option>
          ))}
        </SelectField>

        <div className="flex gap-3">
          <Field
            className="flex-1"
            label="Label français"
            value={themeLabel}
            onChange={e => setThemeLabel(e.target.value)}
            placeholder="Ex : Science-fiction"
            autoFocus={!!initial}
          />
          <Field className="w-24" label="Emoji" value={themeEmoji} onChange={e => setThemeEmoji(e.target.value)} placeholder="🏷️" />
        </div>

        <ErrorMessage>{error}</ErrorMessage>

        <div className="flex flex-wrap gap-3">
          <button type="submit" disabled={saving} className="btn btn-secondary flex-1">
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
          <button type="button" onClick={onClose} className="btn bg-paper">Annuler</button>
        </div>
      </form>
    </div>
  )
}

/* Un tableau large défile dans sa boîte, jamais dans la page. */
function TableBox({ children }) {
  return <div className="panel relative overflow-x-auto">{children}</div>
}

const TH = 'px-4 py-3 text-left text-sm font-bold uppercase text-stone-600'
const LINK = 'inline-flex min-h-11 items-center px-2 font-bold underline underline-offset-4'

/* ── Page principale ─────────────────────────────────────────────────── */
export default function AdminThemesPage() {
  const { user }   = useAuth()
  const navigate   = useNavigate()

  const [mappings,  setMappings]  = useState([])
  const [unmapped,  setUnmapped]  = useState([])
  const [loading,   setLoading]   = useState(true)
  const [activeTab, setActiveTab] = useState('universe')
  const [modal,     setModal]     = useState(null)   // null | 'new' | ThemeMapping
  const [search,    setSearch]    = useState('')

  useEffect(() => {
    if (!user?.isAdmin) { navigate('/'); return }
    Promise.all([getThemeMappings(), getUnmappedBggCategories()])
      .then(([m, u]) => { setMappings(m); setUnmapped(u) })
      .finally(() => setLoading(false))
  }, [user, navigate])

  const closeModal = useCallback(() => setModal(null), [])

  if (!user?.isAdmin) return null

  const reload = () => {
    invalidateThemeCache()
    setLoading(true)
    Promise.all([getThemeMappings(), getUnmappedBggCategories()])
      .then(([m, u]) => { setMappings(m); setUnmapped(u) })
      .finally(() => setLoading(false))
  }

  const handleCreate = (data) => createThemeMapping(data).then(reload)
  const handleUpdate = (id) => (data) => updateThemeMapping(id, data).then(reload)
  const handleDelete = async (id) => {
    if (!confirm('Supprimer ce mapping ?')) return
    await deleteThemeMapping(id)
    reload()
  }

  // Groupes avec compteurs
  const byGroup = {}
  for (const g of GROUP_ORDER) byGroup[g] = mappings.filter(m => m.themeGroup === g)

  const visibleMappings = (activeTab === 'unmapped' ? [] : (byGroup[activeTab] ?? []))
    .filter(m => !search || m.bggCategory.toLowerCase().includes(search.toLowerCase()) || m.themeLabel.toLowerCase().includes(search.toLowerCase()))

  const TABS = [
    ...GROUP_ORDER.map(key => ({ key, ...GROUP_META[key], count: byGroup[key]?.length ?? 0 })),
    { key: 'unmapped', label: 'Non mappées', count: unmapped.length },
  ]

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-4xl leading-tight sm:text-5xl">Taxonomie thématique</h1>
          <p className="mt-1 text-lg text-stone-600">{mappings.length} mappings · inspiré de la taxonomie LudoExplorer</p>
        </div>
        <button type="button" onClick={() => setModal('new')} className="btn btn-primary">Ajouter un mapping</button>
      </div>

      <div role="tablist" aria-label="Groupes" className="tab-list">
        {TABS.map(tab => (
          <button
            key={tab.key}
            type="button"
            role="tab"
            id={`theme-${tab.key}`}
            aria-selected={activeTab === tab.key}
            aria-controls="theme-panneau"
            onClick={() => { setActiveTab(tab.key); setSearch('') }}
            className="tab"
          >
            {tab.emoji && <span aria-hidden="true" className="mr-1.5">{tab.emoji}</span>}
            {tab.label}
            <span className="ml-2 text-sm opacity-80">{tab.count}</span>
          </button>
        ))}
      </div>

      <div id="theme-panneau" role="tabpanel" aria-labelledby={`theme-${activeTab}`} className="flex flex-col gap-4">
        {activeTab !== 'unmapped' && (
          <form role="search" onSubmit={e => e.preventDefault()} className="max-w-md">
            <Field
              label="Filtrer"
              type="search"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Catégorie BGG ou label français"
            />
          </form>
        )}

        {loading && (
          <p role="status" className="flex justify-center py-8">
            <span className="h-6 w-6 animate-spin rounded-full border-[3px] border-stone-300 border-t-brand-red" aria-hidden="true" />
            <span className="sr-only">Chargement</span>
          </p>
        )}

        {!loading && activeTab !== 'unmapped' && (
          visibleMappings.length === 0 ? (
            <p className="panel mx-auto max-w-xl p-6 text-center font-bold">Aucun résultat.</p>
          ) : (
            <TableBox>
              <table className="w-full min-w-[34rem] border-collapse">
                <caption className="sr-only">Mappings du groupe sélectionné</caption>
                <thead className="border-b-[3px] border-ink bg-cream">
                  <tr>
                    <th scope="col" className={TH}>Catégorie BGG</th>
                    <th scope="col" className={TH}>Emoji</th>
                    <th scope="col" className={TH}>Label français</th>
                    <th scope="col" className={TH}>Groupe</th>
                    <th scope="col" className="px-4 py-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y-2 divide-ink/15">
                  {visibleMappings.map(m => (
                    <tr key={m.id}>
                      <th scope="row" className="px-4 py-2 text-left font-bold">{m.bggCategory}</th>
                      <td className="px-4 py-2 text-xl">{m.themeEmoji}</td>
                      <td className="px-4 py-2 text-stone-700">{m.themeLabel}</td>
                      <td className="px-4 py-2">
                        <span className="badge bg-cream">{GROUP_META[m.themeGroup]?.label ?? m.themeGroup}</span>
                      </td>
                      <td className="px-4 py-1">
                        <div className="flex justify-end">
                          <button type="button" onClick={() => setModal(m)} className={`${LINK} text-brand-blue`}>Modifier</button>
                          <button type="button" onClick={() => handleDelete(m.id)} className={`${LINK} text-amber-600`}>Supprimer</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </TableBox>
          )
        )}

        {!loading && activeTab === 'unmapped' && (
          unmapped.length === 0 ? (
            <p className="panel mx-auto max-w-xl p-6 text-center font-bold">Toutes les catégories BGG sont mappées.</p>
          ) : (
            <TableBox>
              <table className="w-full min-w-[22rem] border-collapse">
                <caption className="sr-only">Catégories BGG sans mapping</caption>
                <thead className="border-b-[3px] border-ink bg-cream">
                  <tr>
                    <th scope="col" className={TH}>Catégorie BGG</th>
                    <th scope="col" className={TH}>Jeux</th>
                    <th scope="col" className="px-4 py-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y-2 divide-ink/15">
                  {unmapped.map(({ bgg_category, nb }) => (
                    <tr key={bgg_category}>
                      <th scope="row" className="px-4 py-2 text-left font-bold">{bgg_category}</th>
                      <td className="px-4 py-2 text-stone-700">{Number(nb).toLocaleString('fr')}</td>
                      <td className="px-4 py-1 text-right">
                        <button
                          type="button"
                          onClick={() => setModal({ bggCategory: bgg_category, themeGroup: 'universe', themeLabel: '', themeEmoji: '🏷️', _new: true })}
                          className={`${LINK} text-brand-blue`}
                        >
                          Mapper
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </TableBox>
          )
        )}
      </div>

      {modal && (
        <MappingModal
          initial={modal === 'new' ? null : (modal._new ? { ...modal, id: undefined } : modal)}
          onSave={modal === 'new' || modal._new ? handleCreate : handleUpdate(modal.id)}
          onClose={closeModal}
        />
      )}
    </div>
  )
}
