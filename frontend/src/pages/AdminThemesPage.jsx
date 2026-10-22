import { useState, useEffect } from 'react'
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

const GROUP_COLORS = {
  universe:   'bg-violet-50 text-violet-700 border-violet-200',
  historical: 'bg-amber-50  text-amber-700  border-amber-200',
  game_type:  'bg-blue-50   text-blue-700   border-blue-200',
  society:    'bg-green-50  text-green-700  border-green-200',
  culture:    'bg-rose-50   text-rose-700   border-rose-200',
}

/* ── Modal ajout / édition ───────────────────────────────────────────── */
function MappingModal({ initial, onSave, onClose }) {
  const [bggCategory, setBggCategory] = useState(initial?.bggCategory ?? '')
  const [themeGroup,  setThemeGroup]  = useState(initial?.themeGroup  ?? 'universe')
  const [themeLabel,  setThemeLabel]  = useState(initial?.themeLabel  ?? '')
  const [themeEmoji,  setThemeEmoji]  = useState(initial?.themeEmoji  ?? '🏷️')
  const [saving, setSaving] = useState(false)
  const [error,  setError]  = useState(null)

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
    <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={onClose}>
      <form
        onSubmit={handleSave}
        onClick={e => e.stopPropagation()}
        className="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md space-y-4"
      >
        <h3 className="font-black text-stone-900 text-lg">
          {initial ? 'Modifier le mapping' : 'Ajouter un mapping'}
        </h3>

        <div>
          <label className="text-xs font-semibold text-stone-500 uppercase tracking-wide block mb-1">Catégorie BGG</label>
          <input
            value={bggCategory}
            onChange={e => setBggCategory(e.target.value)}
            placeholder="Ex : Science Fiction"
            disabled={!!initial}
            className="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:bg-stone-50 disabled:text-stone-400"
          />
        </div>

        <div>
          <label className="text-xs font-semibold text-stone-500 uppercase tracking-wide block mb-1">Groupe</label>
          <select
            value={themeGroup}
            onChange={e => setThemeGroup(e.target.value)}
            className="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
          >
            {GROUP_ORDER.map(key => (
              <option key={key} value={key}>
                {GROUP_META[key].emoji} {GROUP_META[key].label}
              </option>
            ))}
          </select>
        </div>

        <div className="flex gap-3">
          <div className="flex-1">
            <label className="text-xs font-semibold text-stone-500 uppercase tracking-wide block mb-1">Label français</label>
            <input
              value={themeLabel}
              onChange={e => setThemeLabel(e.target.value)}
              placeholder="Ex : Science-fiction"
              className="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
            />
          </div>
          <div className="w-24">
            <label className="text-xs font-semibold text-stone-500 uppercase tracking-wide block mb-1">Emoji</label>
            <input
              value={themeEmoji}
              onChange={e => setThemeEmoji(e.target.value)}
              placeholder="🏷️"
              className="w-full px-3 py-2 border border-stone-300 rounded-xl text-sm text-center focus:outline-none focus:ring-2 focus:ring-amber-400"
            />
          </div>
        </div>

        {error && <p className="text-sm text-red-600">{error}</p>}

        <div className="flex gap-2 pt-2">
          <button type="submit" disabled={saving}
            className="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 rounded-xl text-sm transition-colors disabled:opacity-60">
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
          <button type="button" onClick={onClose}
            className="px-4 py-2.5 rounded-xl border border-stone-200 text-stone-600 hover:bg-stone-50 text-sm">
            Annuler
          </button>
        </div>
      </form>
    </div>
  )
}

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
    { key: 'unmapped', label: 'Non mappées', emoji: '⚠️', count: unmapped.length },
  ]

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-black text-stone-900">Taxonomie thématique</h1>
          <p className="text-stone-400 text-sm mt-0.5">
            {mappings.length} mappings · inspiré de la taxonomie LudoExplorer
          </p>
        </div>
        <button
          onClick={() => setModal('new')}
          className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-sm rounded-xl transition-colors"
        >
          + Ajouter
        </button>
      </div>

      {/* Onglets groupes */}
      <div className="flex gap-1 border-b border-stone-200 mb-5 overflow-x-auto">
        {TABS.map(tab => (
          <button
            key={tab.key}
            onClick={() => { setActiveTab(tab.key); setSearch('') }}
            className={
              'flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px whitespace-nowrap ' +
              (activeTab === tab.key
                ? 'border-amber-500 text-amber-700'
                : 'border-transparent text-stone-500 hover:text-stone-700')
            }
          >
            <span>{tab.emoji}</span>
            <span>{tab.label}</span>
            <span className={`text-xs px-1.5 py-0.5 rounded-full font-bold ml-1 ${
              activeTab === tab.key ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-500'
            }`}>{tab.count}</span>
          </button>
        ))}
      </div>

      {/* Recherche dans l'onglet courant */}
      {activeTab !== 'unmapped' && (
        <div className="mb-4">
          <input
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Filtrer par catégorie BGG ou label…"
            className="w-full max-w-sm px-3 py-2 text-sm border border-stone-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400"
          />
        </div>
      )}

      {loading && (
        <div className="flex justify-center py-12">
          <div className="w-6 h-6 border-2 border-stone-300 border-t-amber-500 rounded-full animate-spin" />
        </div>
      )}

      {/* Mappings */}
      {!loading && activeTab !== 'unmapped' && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
          {visibleMappings.length === 0 ? (
            <p className="text-center text-stone-400 py-10 text-sm">Aucun résultat</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-stone-50 border-b border-stone-200">
                <tr>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Catégorie BGG</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Emoji</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Label français</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Groupe</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {visibleMappings.map(m => (
                  <tr key={m.id} className="hover:bg-stone-50 transition-colors">
                    <td className="px-4 py-3 font-medium text-stone-800">{m.bggCategory}</td>
                    <td className="px-4 py-3 text-xl">{m.themeEmoji}</td>
                    <td className="px-4 py-3 text-stone-600">{m.themeLabel}</td>
                    <td className="px-4 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded-full border font-medium ${GROUP_COLORS[m.themeGroup] ?? 'bg-stone-100 text-stone-600 border-stone-200'}`}>
                        {GROUP_META[m.themeGroup]?.emoji} {GROUP_META[m.themeGroup]?.label ?? m.themeGroup}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2 justify-end">
                        <button onClick={() => setModal(m)}
                          className="text-xs text-amber-600 hover:underline font-medium">
                          Modifier
                        </button>
                        <button onClick={() => handleDelete(m.id)}
                          className="text-xs text-red-500 hover:underline font-medium">
                          Suppr.
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Catégories BGG non mappées */}
      {!loading && activeTab === 'unmapped' && (
        <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
          {unmapped.length === 0 ? (
            <p className="text-center text-stone-400 py-10 text-sm">
              🎉 Toutes les catégories BGG sont mappées !
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-stone-50 border-b border-stone-200">
                <tr>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Catégorie BGG</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-stone-500 uppercase tracking-wide">Nb jeux</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-stone-100">
                {unmapped.map(({ bgg_category, nb }) => (
                  <tr key={bgg_category} className="hover:bg-stone-50">
                    <td className="px-4 py-3 font-medium text-stone-800">{bgg_category}</td>
                    <td className="px-4 py-3 text-stone-400">{Number(nb).toLocaleString('fr')}</td>
                    <td className="px-4 py-3 text-right">
                      <button
                        onClick={() => setModal({ bggCategory: bgg_category, themeGroup: 'universe', themeLabel: '', themeEmoji: '🏷️', _new: true })}
                        className="text-xs text-amber-600 hover:underline font-medium"
                      >
                        + Mapper
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Modal */}
      {modal && (
        <MappingModal
          initial={modal === 'new' ? null : (modal._new ? { ...modal, id: undefined } : modal)}
          onSave={modal === 'new' || modal._new ? handleCreate : handleUpdate(modal.id)}
          onClose={() => setModal(null)}
        />
      )}
    </div>
  )
}
