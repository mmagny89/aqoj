import { describe, it, expect } from 'vitest'
import { momentDeLaJournee } from './moment'

const a = (h) => new Date(2026, 10, 21, h, 0)

describe('momentDeLaJournee', () => {
  it.each([[5, 'ce matin'], [11, 'ce matin'], [12, 'ce midi'], [14, 'cet après-midi'], [18, 'ce soir'], [22, 'ce soir'], [23, 'cette nuit'], [3, 'cette nuit']])('%i h → %s', (h, attendu) => {
    expect(momentDeLaJournee(a(h))).toBe(attendu)
  })
})
