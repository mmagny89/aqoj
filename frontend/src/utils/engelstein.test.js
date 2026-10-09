import { describe, expect, it } from 'vitest'
import { familyColor, familyLabel, isEngine, isExtra, ENGINE_COLORS, SUPPORT_COLOR } from './engelstein'

describe('engelstein', () => {
  it('reconnaît les moteurs centraux et les familles extra', () => {
    expect(isEngine('deck_building')).toBe(true)
    expect(isEngine('solo')).toBe(false)
    expect(isExtra('solo')).toBe(true)
  })

  it('donne le libellé français, ou la clé brute si la famille est inconnue', () => {
    expect(familyLabel('auction')).toBe('Enchères')
    expect(familyLabel('inconnue')).toBe('inconnue')
  })

  it('colore un moteur, et retombe sur la couleur neutre pour une famille de support', () => {
    expect(familyColor('auction')).toBe(ENGINE_COLORS.auction)
    expect(familyColor('movement')).toBe(SUPPORT_COLOR)
  })
})
