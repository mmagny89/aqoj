import { useState, useEffect, useCallback } from 'react'
import {
  getMechanicMappings,
  createMechanicMapping,
  updateMechanicMapping,
  deleteMechanicMapping,
  recomputeMechanicFamilies,
  getUnmappedMechanics,
} from '../api'
import { useAuth } from '../context/AuthContext'
import { useNavigate } from 'react-router-dom'

const ENGINE_FAMILIES = [
  'worker_placement',
  'deck_building',
  'engine_building',
  'area_control',
  'hand_management',
  'auction',
]

const EXTRA_FAMILIES_LIST = ['solo', 'real_time', 'dexterity', 'legacy']

const EXTRA_COLORS_ADMIN = {
  solo:       'bg-sky-50 border-sky-300 text-sky-900',
  real_time:  'bg-orange-50 border-orange-300 text-orange-900',
  dexterity:  'bg-rose-50 border-rose-300 text-rose-900',
  legacy:     'bg-indigo-50 border-indigo-300 text-indigo-900',
}

const EXTRA_HEADER_COLORS = {
  solo:       'bg-sky-100 text-sky-800',
  real_time:  'bg-orange-100 text-orange-800',
  dexterity:  'bg-rose-100 text-rose-800',
  legacy:     'bg-indigo-100 text-indigo-800',
}

const EXTRA_LABELS = {
  solo:       'Solo / Solitaire',
  real_time:  'Temps Réel',
  dexterity:  'Adresse / Dextérité',
  legacy:     'Legacy / Campagne',
}

const ENGINE_COLORS = {
  worker_placement: 'bg-amber-50 border-amber-300 text-amber-900',
  deck_building:    'bg-pink-50 border-pink-300 text-pink-900',
  engine_building:  'bg-purple-50 border-purple-300 text-purple-900',
  area_control:     'bg-red-50 border-red-300 text-red-900',
  hand_management:  'bg-blue-50 border-blue-300 text-blue-900',
  auction:          'bg-green-50 border-green-300 text-green-900',
}

const ENGINE_HEADER_COLORS = {
  worker_placement: 'bg-amber-100 text-amber-800',
  deck_building:    'bg-pink-100 text-pink-800',
  engine_building:  'bg-purple-100 text-purple-800',
  area_control:     'bg-red-100 text-red-800',
  hand_management:  'bg-blue-100 text-blue-800',
  auction:          'bg-green-100 text-green-800',
}

const EMPTY_FORM = { bggMechanic: '', engelsteinFamily: '', description: '' }

export default function AdminMechanicsPage() {
  const { user } = useAuth()
  const navigate = useNavigate()

  const [mappings, setMappings]         = useState([])
  const [families, setFamilies]         = useState({})
  const [unmapped, setUnmapped]         = useState([])
  const [loading, setLoading]           = useState(true)
  const [error, setError]               = useState(null)
  const [activeTab, setActiveTab]       = useState('engines') // 'engines' | 'support' | 'unmapped'

  const [editingId, setEditingId]       = useState(null)
  const [form, setForm]                 = useState(EMPTY_FORM)
  const [formError, setFormError]       = useState(null)
  const [saving, setSaving]             = useState(false)

  const [recomputing, setRecomputing]   = useState(false)
  const [recomputeResult, setRecomputeResult] = useState(null)

  const [search, setSearch]             = useState('')

  useEffect(() => {
    if (!user?.isAdmin) navigate('/')
  }, [user, navigate])

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [data, unmappedData] = await Promise.all([
        getMechanicMappings(),
        getUnmappedMechanics(),
      ])
      setMappings(data.mappings)
      setFamilies(data.families)
      setUnmapped(unmappedData)
    } catch (e) {
      setError(e.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const startEdit = (mapping) => {
    setEditingId(mapping.id)
    setForm({
      bggMechanic: mapping.bggMechanic,
      engelsteinFamily: mapping.engelsteinFamily,
      description: mapping.description ?? '',
    })
    setFormError(null)
  }

  const startCreate = (prefillMechanic = '', prefillFamily = '') => {
    setEditingId('new')
    setForm({ bggMechanic: prefillMechanic, engelsteinFamily: prefillFamily, description: '' })
    setFormError(null)
  }

  const cancelEdit = () => {
    setEditingId(null)
    setForm(EMPTY_FORM)
    setFormError(null)
  }

  const handleSave = async () => {
    setSaving(true)
    setFormError(null)
    try {
      const payload = {
        bggMechanic: form.bggMechanic.trim(),
        engelsteinFamily: form.engelsteinFamily,
        description: form.description.trim() || null,
      }
      if (editingId === 'new') {
        await createMechanicMapping(payload)
      } else {
        await updateMechanicMapping(editingId, payload)
      }
      cancelEdit()
      await load()
    } catch (e) {
      setFormError(e.message)
    } finally {
      setSaving(false)
    }
  }

  const handleRecompute = async () => {
    if (!confirm('Recalculer les familles Engelstein pour tous les jeux en base ?')) return
    setRecomputing(true)
    setRecomputeResult(null)
    try {
      const result = await recomputeMechanicFamilies()
      setRecomputeResult(`✅ ${result.updated} jeux mis à jour.`)
    } catch (e) {
      setRecomputeResult(`Erreur : ${e.message}`)
    } finally {
      setRecomputing(false)
    }
  }

  const handleDelete = async (id, mechanic) => {
    if (!confirm(`Supprimer le mapping pour "${mechanic}" ?`)) return
    await deleteMechanicMapping(id)
    await load()
  }

  const mapUnmapped = (mechanic) => {
    setActiveTab('engines')
    startCreate(mechanic, '')
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 50)
  }

  const filterMappings = (items) => {
    if (!search) return items
    const q = search.toLowerCase()
    return items.filter(m =>
      m.bggMechanic.toLowerCase().includes(q) ||
      (m.description ?? '').toLowerCase().includes(q)
    )
  }

  const engineMappings  = filterMappings(mappings.filter(m => ENGINE_FAMILIES.includes(m.engelsteinFamily)))
  const supportMappings = filterMappings(mappings.filter(m => !ENGINE_FAMILIES.includes(m.engelsteinFamily) && !EXTRA_FAMILIES_LIST.includes(m.engelsteinFamily)))
  const extraMappings   = filterMappings(mappings.filter(m => EXTRA_FAMILIES_LIST.includes(m.engelsteinFamily)))
  const filteredUnmapped = search
    ? unmapped.filter(u => u.mechanic.toLowerCase().includes(search.toLowerCase()))
    : unmapped

  const tabs = [
    { key: 'engines',  label: '⚙️ Moteurs centraux',   count: engineMappings.length,  badge: null },
    { key: 'support',  label: '🔧 Familles de support', count: supportMappings.length, badge: null },
    { key: 'extra',    label: '🏷 Modes & extras',       count: extraMappings.length,   badge: null },
    { key: 'unmapped', label: '⚠️ Non mappées',          count: filteredUnmapped.length, badge: unmapped.length > 0 ? unmapped.length : null },
  ]

  if (!user?.isAdmin) return null

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">

      {/* Header */}
      <div className="flex items-start justify-between mb-6 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-stone-900">Mappings mécaniques Engelstein</h1>
          <p className="text-stone-500 text-sm mt-1">
            Association entre les mécaniques BGG et les familles du modèle d'Engelstein.
          </p>
        </div>
        <div className="flex gap-2 flex-shrink-0">
          <button
            onClick={handleRecompute}
            disabled={recomputing}
            className="border border-stone-300 hover:bg-stone-100 disabled:opacity-50 px-3 py-2 rounded-lg text-sm font-medium"
          >
            {recomputing ? 'Recalcul…' : '↺ Recalculer'}
          </button>
          <button
            onClick={() => startCreate()}
            className="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 rounded-lg text-sm font-medium"
          >
            + Ajouter
          </button>
        </div>
      </div>

      {recomputeResult && (
        <div className="mb-4 text-sm px-4 py-2 bg-green-50 border border-green-200 text-green-800 rounded-lg">
          {recomputeResult}
        </div>
      )}

      {/* Formulaire */}
      {editingId !== null && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
          <h2 className="font-semibold mb-4 text-amber-900">
            {editingId === 'new' ? 'Nouveau mapping' : 'Modifier le mapping'}
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-stone-600 mb-1">Mécanique BGG *</label>
              <input
                type="text"
                value={form.bggMechanic}
                onChange={e => setForm(f => ({ ...f, bggMechanic: e.target.value }))}
                placeholder="ex: Worker Placement"
                className="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-stone-600 mb-1">Famille Engelstein *</label>
              <select
                value={form.engelsteinFamily}
                onChange={e => setForm(f => ({ ...f, engelsteinFamily: e.target.value }))}
                className="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
              >
                <option value="">-- Choisir --</option>
                <optgroup label="⚙️ Moteurs centraux">
                  {Object.entries(families).filter(([k]) => ENGINE_FAMILIES.includes(k)).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </optgroup>
                <optgroup label="🔧 Familles de support">
                  {Object.entries(families).filter(([k]) => !ENGINE_FAMILIES.includes(k)).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </optgroup>
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs font-medium text-stone-600 mb-1">Description (optionnel)</label>
              <input
                type="text"
                value={form.description}
                onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                placeholder="Courte description de la mécanique"
                className="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
              />
            </div>
          </div>
          {formError && <p className="text-red-600 text-sm mt-3">{formError}</p>}
          <div className="flex gap-3 mt-4">
            <button
              onClick={handleSave}
              disabled={saving}
              className="bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium"
            >
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
            <button onClick={cancelEdit} className="border border-stone-300 hover:bg-stone-100 px-4 py-2 rounded-lg text-sm">
              Annuler
            </button>
          </div>
        </div>
      )}

      {/* Recherche + onglets */}
      <div className="flex flex-col sm:flex-row gap-3 mb-5">
        <input
          type="text"
          placeholder="Rechercher une mécanique…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="flex-1 border border-stone-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
        />
      </div>

      <div className="flex gap-1 mb-6 border-b border-stone-200">
        {tabs.map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-all flex items-center gap-2 ${
              activeTab === tab.key
                ? 'border-amber-500 text-amber-700 bg-amber-50'
                : 'border-transparent text-stone-500 hover:text-stone-700 hover:bg-stone-50'
            }`}
          >
            {tab.label}
            <span className={`text-xs px-1.5 py-0.5 rounded-full font-bold ${
              tab.key === 'unmapped'
                ? 'bg-orange-100 text-orange-700'
                : 'bg-stone-100 text-stone-500'
            }`}>
              {tab.count}
            </span>
          </button>
        ))}
      </div>

      {loading && <p className="text-stone-400 text-sm">Chargement…</p>}
      {error && <p className="text-red-600 text-sm">{error}</p>}

      {/* Onglet : Moteurs centraux */}
      {activeTab === 'engines' && !loading && (
        <div className="space-y-4">
          <p className="text-xs text-stone-400 mb-4">
            Ces 6 familles définissent le moteur principal du jeu — la boucle de gameplay centrale selon le modèle Engelstein.
            Une mécanique BGG mappée ici peut qualifier le jeu comme appartenant à ce moteur.
          </p>
          {ENGINE_FAMILIES.map(key => {
            const label = families[key] ?? key
            const items = engineMappings.filter(m => m.engelsteinFamily === key)
            const color = ENGINE_COLORS[key] ?? 'bg-stone-50 border-stone-200 text-stone-900'
            const headerColor = ENGINE_HEADER_COLORS[key] ?? 'bg-stone-100 text-stone-700'
            return (
              <div key={key} className={`rounded-xl border-2 overflow-hidden ${color}`}>
                <div className={`px-4 py-3 flex items-center justify-between ${headerColor}`}>
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-sm">{label}</span>
                    <span className="text-xs opacity-70">({items.length} mécaniques)</span>
                  </div>
                  <button
                    onClick={() => startCreate('', key)}
                    className="text-xs font-medium opacity-70 hover:opacity-100 transition-opacity"
                  >
                    + Ajouter
                  </button>
                </div>
                {items.length === 0 ? (
                  <p className="px-4 py-3 text-sm opacity-50 italic">Aucune mécanique BGG mappée.</p>
                ) : (
                  <div className="divide-y divide-black/5">
                    {items.map(m => (
                      <div key={m.id} className="px-4 py-2.5 flex items-center justify-between gap-3">
                        <div>
                          <span className="text-sm font-medium">{m.bggMechanic}</span>
                          {m.description && (
                            <span className="ml-2 text-xs opacity-60">{m.description}</span>
                          )}
                        </div>
                        <div className="flex gap-3 flex-shrink-0">
                          <button onClick={() => startEdit(m)} className="text-xs opacity-50 hover:opacity-100">Modifier</button>
                          <button onClick={() => handleDelete(m.id, m.bggMechanic)} className="text-xs opacity-50 hover:text-red-600 hover:opacity-100">Supprimer</button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}

      {/* Onglet : Familles de support */}
      {activeTab === 'support' && !loading && (
        <div className="space-y-4">
          <p className="text-xs text-stone-400 mb-4">
            Ces 8 familles enrichissent la fiche d'un jeu mais ne définissent pas son moteur principal.
            Elles apparaissent en secondary tags sur les fiches et dans les filtres.
          </p>
          {Object.entries(families)
            .filter(([k]) => !ENGINE_FAMILIES.includes(k) && !EXTRA_FAMILIES_LIST.includes(k))
            .map(([key, label]) => {
              const items = supportMappings.filter(m => m.engelsteinFamily === key)
              return (
                <div key={key} className="rounded-xl border border-stone-200 overflow-hidden">
                  <div className="px-4 py-3 bg-stone-50 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-sm text-stone-700">{label}</span>
                      <span className="text-xs text-stone-400">({items.length})</span>
                    </div>
                    <button
                      onClick={() => startCreate('', key)}
                      className="text-xs text-stone-400 hover:text-amber-600 font-medium"
                    >
                      + Ajouter
                    </button>
                  </div>
                  {items.length === 0 ? (
                    <p className="px-4 py-3 text-sm text-stone-400 italic">Aucune mécanique BGG mappée.</p>
                  ) : (
                    <div className="divide-y divide-stone-100">
                      {items.map(m => (
                        <div key={m.id} className="px-4 py-2.5 flex items-center justify-between gap-3">
                          <div>
                            <span className="text-sm font-medium text-stone-800">{m.bggMechanic}</span>
                            {m.description && (
                              <span className="ml-2 text-xs text-stone-400">{m.description}</span>
                            )}
                          </div>
                          <div className="flex gap-3 flex-shrink-0">
                            <button onClick={() => startEdit(m)} className="text-xs text-stone-400 hover:text-amber-600">Modifier</button>
                            <button onClick={() => handleDelete(m.id, m.bggMechanic)} className="text-xs text-stone-400 hover:text-red-600">Supprimer</button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
        </div>
      )}

      {/* Onglet : Modes & extras */}
      {activeTab === 'extra' && !loading && (
        <div className="space-y-4">
          <p className="text-xs text-stone-400 mb-4">
            Ces familles sont hors taxonomie Engelstein : elles décrivent des <strong>modes de jeu</strong> ou des
            <strong> caractéristiques transversales</strong> (solo, temps réel, dextérité, legacy).
            Elles ne peuvent jamais être un moteur central.
          </p>
          {EXTRA_FAMILIES_LIST.map(key => {
            const label = EXTRA_LABELS[key] ?? key
            const items = extraMappings.filter(m => m.engelsteinFamily === key)
            const color = EXTRA_COLORS_ADMIN[key] ?? 'bg-stone-50 border-stone-200 text-stone-900'
            const headerColor = EXTRA_HEADER_COLORS[key] ?? 'bg-stone-100 text-stone-700'
            return (
              <div key={key} className={`rounded-xl border-2 overflow-hidden ${color}`}>
                <div className={`px-4 py-3 flex items-center justify-between ${headerColor}`}>
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-sm">{label}</span>
                    <span className="text-xs opacity-70">({items.length} mécaniques)</span>
                  </div>
                  <button
                    onClick={() => startCreate('', key)}
                    className="text-xs font-medium opacity-70 hover:opacity-100 transition-opacity"
                  >
                    + Ajouter
                  </button>
                </div>
                {items.length === 0 ? (
                  <p className="px-4 py-3 text-sm opacity-50 italic">Aucune mécanique BGG mappée.</p>
                ) : (
                  <div className="divide-y divide-black/5">
                    {items.map(m => (
                      <div key={m.id} className="px-4 py-2.5 flex items-center justify-between gap-3">
                        <div>
                          <span className="text-sm font-medium">{m.bggMechanic}</span>
                          {m.description && (
                            <span className="ml-2 text-xs opacity-60">{m.description}</span>
                          )}
                        </div>
                        <div className="flex gap-3 flex-shrink-0">
                          <button onClick={() => startEdit(m)} className="text-xs opacity-50 hover:opacity-100">Modifier</button>
                          <button onClick={() => handleDelete(m.id, m.bggMechanic)} className="text-xs opacity-50 hover:text-red-600 hover:opacity-100">Supprimer</button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}

      {/* Onglet : Non mappées */}
      {activeTab === 'unmapped' && !loading && (
        <div>
          <p className="text-xs text-stone-400 mb-4">
            Ces mécaniques BGG existent dans des jeux de la base mais n'ont pas encore de mapping Engelstein.
            Triées par nombre de jeux concernés — les plus fréquentes en priorité.
          </p>
          {filteredUnmapped.length === 0 ? (
            <div className="text-center py-12 text-stone-400">
              <div className="text-4xl mb-3">✅</div>
              <p className="font-medium">Toutes les mécaniques sont mappées !</p>
            </div>
          ) : (
            <div className="border border-orange-200 rounded-xl overflow-hidden">
              <div className="bg-orange-50 px-4 py-3 text-xs font-semibold text-orange-700 uppercase tracking-wide flex justify-between">
                <span>Mécanique BGG</span>
                <span>Jeux</span>
              </div>
              <div className="divide-y divide-stone-100">
                {filteredUnmapped.map(({ mechanic, game_count }) => (
                  <div key={mechanic} className="px-4 py-2.5 flex items-center justify-between gap-3 bg-white hover:bg-orange-50 transition-colors">
                    <div className="flex items-center gap-3">
                      <span className="w-2 h-2 rounded-full bg-orange-300 flex-shrink-0" />
                      <span className="text-sm font-medium text-stone-800">{mechanic}</span>
                    </div>
                    <div className="flex items-center gap-4">
                      <span className="text-xs text-stone-400 font-medium">{game_count} jeu{game_count > 1 ? 'x' : ''}</span>
                      <button
                        onClick={() => mapUnmapped(mechanic)}
                        className="text-xs font-medium text-amber-600 hover:text-amber-800 border border-amber-300 hover:border-amber-500 px-2 py-1 rounded-lg transition-all"
                      >
                        Mapper →
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
