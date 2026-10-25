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
import { ErrorMessage, Field, SelectField } from '../components/forms'

const ENGINE_FAMILIES = [
  'worker_placement',
  'deck_building',
  'engine_building',
  'area_control',
  'hand_management',
  'auction',
]

const EXTRA_FAMILIES_LIST = ['solo', 'real_time', 'dexterity', 'legacy']

const EXTRA_LABELS = {
  solo:       'Solo / Solitaire',
  real_time:  'Temps Réel',
  dexterity:  'Adresse / Dextérité',
  legacy:     'Legacy / Campagne',
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
      setRecomputeResult(`${result.updated} jeux mis à jour.`)
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
    { key: 'engines',  label: 'Moteurs centraux',    count: engineMappings.length },
    { key: 'support',  label: 'Familles de support', count: supportMappings.length },
    { key: 'extra',    label: 'Modes et extras',     count: extraMappings.length },
    { key: 'unmapped', label: 'Non mappées',         count: filteredUnmapped.length },
  ]

  if (!user?.isAdmin) return null

  const supportKeys = Object.keys(families).filter(k => !ENGINE_FAMILIES.includes(k) && !EXTRA_FAMILIES_LIST.includes(k))

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-4xl leading-tight sm:text-5xl">Mappings mécaniques</h1>
          <p className="mt-1 text-lg text-stone-600">
            Association entre les mécaniques BGG et les familles du modèle d'Engelstein.
          </p>
        </div>
        <div className="flex flex-wrap gap-3">
          <button type="button" onClick={handleRecompute} disabled={recomputing} className="btn bg-paper">
            {recomputing ? 'Recalcul…' : 'Recalculer les familles'}
          </button>
          <button type="button" onClick={() => startCreate()} className="btn btn-primary">
            Ajouter un mapping
          </button>
        </div>
      </div>

      {recomputeResult && (
        <p role="status" className="rounded-xl border-[3px] border-ink bg-cream px-4 py-3 font-bold">{recomputeResult}</p>
      )}

      {editingId !== null && (
        <section aria-labelledby="titre-mapping" className="panel flex flex-col gap-4 bg-amber-100 p-5">
          <h2 id="titre-mapping" className="text-2xl">
            {editingId === 'new' ? 'Nouveau mapping' : 'Modifier le mapping'}
          </h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field
              label="Mécanique BGG (obligatoire)"
              type="text"
              value={form.bggMechanic}
              onChange={e => setForm(f => ({ ...f, bggMechanic: e.target.value }))}
              placeholder="Ex : Worker Placement"
            />
            <SelectField
              label="Famille Engelstein (obligatoire)"
              value={form.engelsteinFamily}
              onChange={e => setForm(f => ({ ...f, engelsteinFamily: e.target.value }))}
            >
              <option value="">Choisir…</option>
              <optgroup label="Moteurs centraux">
                {Object.entries(families).filter(([k]) => ENGINE_FAMILIES.includes(k)).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </optgroup>
              <optgroup label="Familles de support">
                {Object.entries(families).filter(([k]) => !ENGINE_FAMILIES.includes(k)).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </optgroup>
            </SelectField>
            <Field
              className="sm:col-span-2"
              label="Description"
              hint="(facultative)"
              type="text"
              value={form.description}
              onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
              placeholder="Courte description de la mécanique"
            />
          </div>
          <ErrorMessage>{formError}</ErrorMessage>
          <div className="flex flex-wrap gap-3">
            <button type="button" onClick={handleSave} disabled={saving} className="btn btn-secondary">
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
            <button type="button" onClick={cancelEdit} className="btn bg-paper">Annuler</button>
          </div>
        </section>
      )}

      <form role="search" onSubmit={e => e.preventDefault()}>
        <Field
          label="Rechercher une mécanique"
          type="search"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Nom BGG ou description"
        />
      </form>

      <div role="tablist" aria-label="Familles" className="tab-list">
        {tabs.map(tab => (
          <button
            key={tab.key}
            type="button"
            role="tab"
            id={`meca-${tab.key}`}
            aria-selected={activeTab === tab.key}
            aria-controls="meca-panneau"
            onClick={() => setActiveTab(tab.key)}
            className="tab"
          >
            {tab.label}
            <span className="ml-2 text-sm opacity-80">{tab.count}</span>
          </button>
        ))}
      </div>

      <div id="meca-panneau" role="tabpanel" aria-labelledby={`meca-${activeTab}`} className="flex flex-col gap-4">
        {loading && <p role="status" className="text-stone-600">Chargement…</p>}
        <ErrorMessage>{error}</ErrorMessage>

        {activeTab === 'engines' && !loading && (
          <>
            <p className="text-stone-700">
              Ces 6 familles définissent le moteur principal du jeu : la boucle de gameplay centrale selon le modèle Engelstein.
              Une mécanique BGG mappée ici peut qualifier le jeu comme appartenant à ce moteur.
            </p>
            {ENGINE_FAMILIES.map(key => (
              <MappingGroup
                key={key}
                title={families[key] ?? key}
                items={engineMappings.filter(m => m.engelsteinFamily === key)}
                onAdd={() => startCreate('', key)}
                onEdit={startEdit}
                onDelete={handleDelete}
              />
            ))}
          </>
        )}

        {activeTab === 'support' && !loading && (
          <>
            <p className="text-stone-700">
              Ces familles enrichissent la fiche d'un jeu mais ne définissent pas son moteur principal. Elles apparaissent
              en étiquettes secondaires sur les fiches et dans les filtres.
            </p>
            {supportKeys.map(key => (
              <MappingGroup
                key={key}
                title={families[key]}
                items={supportMappings.filter(m => m.engelsteinFamily === key)}
                onAdd={() => startCreate('', key)}
                onEdit={startEdit}
                onDelete={handleDelete}
              />
            ))}
          </>
        )}

        {activeTab === 'extra' && !loading && (
          <>
            <p className="text-stone-700">
              Ces familles sont hors taxonomie Engelstein : elles décrivent des <strong>modes de jeu</strong> ou des{' '}
              <strong>caractéristiques transversales</strong> (solo, temps réel, dextérité, legacy). Elles ne peuvent jamais
              être un moteur central.
            </p>
            {EXTRA_FAMILIES_LIST.map(key => (
              <MappingGroup
                key={key}
                title={EXTRA_LABELS[key] ?? key}
                items={extraMappings.filter(m => m.engelsteinFamily === key)}
                onAdd={() => startCreate('', key)}
                onEdit={startEdit}
                onDelete={handleDelete}
              />
            ))}
          </>
        )}

        {activeTab === 'unmapped' && !loading && (
          <>
            <p className="text-stone-700">
              Ces mécaniques BGG existent dans des jeux de la base mais n'ont pas encore de mapping. Les plus fréquentes
              viennent en premier.
            </p>
            {filteredUnmapped.length === 0 ? (
              <p className="panel mx-auto max-w-xl p-6 text-center font-bold">Toutes les mécaniques sont mappées.</p>
            ) : (
              <ul className="panel m-0 list-none divide-y-2 divide-ink/15 overflow-hidden p-0">
                {filteredUnmapped.map(({ mechanic, game_count }) => (
                  <li key={mechanic} className="flex flex-wrap items-center justify-between gap-3 px-4 py-2">
                    <span className="min-w-0 break-words font-bold">{mechanic}</span>
                    <span className="flex items-center gap-3">
                      <span className="text-stone-600">{game_count} jeu{game_count > 1 ? 'x' : ''}</span>
                      <button type="button" onClick={() => mapUnmapped(mechanic)} className="btn btn-secondary !min-h-11 text-sm">
                        Mapper
                      </button>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>
    </div>
  )
}

/* ── Un groupe de mappings : titre, ajout, liste ─────────────────────── */
function MappingGroup({ title, items, onAdd, onEdit, onDelete }) {
  return (
    <section className="panel overflow-hidden" aria-label={title}>
      <div className="flex flex-wrap items-center justify-between gap-2 border-b-[3px] border-ink bg-cream px-4 py-2">
        <h3 className="font-sans text-lg font-bold">
          {title} <span className="font-normal text-stone-600">({items.length})</span>
        </h3>
        <button type="button" onClick={onAdd} className="inline-flex min-h-11 items-center font-bold text-brand-blue underline underline-offset-4">
          Ajouter
        </button>
      </div>
      {items.length === 0 ? (
        <p className="px-4 py-3 italic text-stone-600">Aucune mécanique BGG mappée.</p>
      ) : (
        <ul className="m-0 list-none divide-y-2 divide-ink/15 p-0">
          {items.map(m => (
            <li key={m.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-1.5">
              <span className="min-w-0">
                <span className="font-bold">{m.bggMechanic}</span>
                {m.description && <span className="ml-2 text-sm text-stone-600">{m.description}</span>}
              </span>
              <span className="flex gap-1">
                <button type="button" onClick={() => onEdit(m)} className="inline-flex min-h-11 items-center px-2 font-bold text-brand-blue underline underline-offset-4">Modifier</button>
                <button type="button" onClick={() => onDelete(m.id, m.bggMechanic)} className="inline-flex min-h-11 items-center px-2 font-bold text-amber-600 underline underline-offset-4">Supprimer</button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
