import { useState } from 'react'
import { saveTaste } from '../api'
import { ENGINES, familyLabel } from '../utils/engelstein'

// Moteurs centraux + les mécaniques de support qu'un joueur reconnaît sans vocabulaire de spécialiste.
const TASTES = [...Object.keys(ENGINES), 'cooperation', 'card_play', 'spatial_placement', 'resource_management', 'uncertainty']
export const TASTES_MAX = 6

/**
 * Questionnaire de goûts : jusqu'à six styles, enregistrés sur le compte.
 * `allowEmpty` permet de tout décocher pour effacer ses goûts (page du compte).
 */
export default function TasteForm({ initial, onSaved, allowEmpty = false, labelledBy }) {
  const [selected, setSelected] = useState(initial)
  const [saving, setSaving]     = useState(false)
  const [error, setError]       = useState(null)
  const [saved, setSaved]       = useState(false)

  const toggle = (key) => {
    setSaved(false)
    setSelected(prev => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key])
  }

  const submit = (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    saveTaste(selected)
      .then(result => { setSaved(true); onSaved?.(result) })
      .catch(() => setError('Impossible d’enregistrer vos goûts, réessayez.'))
      .finally(() => setSaving(false))
  }

  return (
    <form onSubmit={submit} aria-labelledby={labelledBy} className="flex flex-col gap-4">
      <ul className="m-0 flex list-none flex-wrap gap-2 p-0">
        {TASTES.map(key => {
          const on = selected.includes(key)
          return (
            <li key={key}>
              <button type="button" className="chip" aria-pressed={on} disabled={!on && selected.length >= TASTES_MAX} onClick={() => toggle(key)}>
                {familyLabel(key)}
              </button>
            </li>
          )
        })}
      </ul>
      {error && <p role="alert" className="font-bold text-brand-red">{error}</p>}
      {saved && <p role="status" className="font-bold text-brand-green">Goûts enregistrés.</p>}
      <div>
        <button type="submit" className="btn btn-primary" disabled={saving || (!allowEmpty && selected.length === 0)}>
          {saving ? 'Enregistrement…' : 'Enregistrer mes goûts'}
        </button>
      </div>
    </form>
  )
}
