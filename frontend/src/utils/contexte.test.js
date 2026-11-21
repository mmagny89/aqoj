import { describe, expect, it } from 'vitest'
import { activeCount, filtersFromParams, filtersToApi, filtersToParams, fitBadge, NO_FILTERS, unknownFields } from './contexte'

describe('filtres de contexte', () => {
  it('lisent l’adresse et ignorent les valeurs inconnues', () => {
    const filters = filtersFromParams(new URLSearchParams('joueurs=4&nombre=ideal&duree=60&poids=leger&mode=coop&age=8&joue=non'))
    expect(filters).toEqual({ players: 4, fit: 'best', maxTime: 60, weight: 'light', coop: true, age: 8, played: false })
    expect(filtersFromParams(new URLSearchParams('joueurs=abc&nombre=parfait&poids=enorme&mode=x&joue=peut-etre'))).toEqual(NO_FILTERS)
  })

  it('écrivent l’adresse sans toucher aux autres paramètres, et retirent les filtres vides', () => {
    const params = filtersToParams({ ...NO_FILTERS, players: 2, weight: 'heavy', coop: false }, new URLSearchParams('q=catan&duree=30'))
    expect(params.toString()).toBe('q=catan&joueurs=2&poids=costaud&mode=competitif')
  })

  it('n’écrit « idéal » que si un nombre de joueurs est choisi', () => {
    expect(filtersToParams({ ...NO_FILTERS, fit: 'best' }, new URLSearchParams()).toString()).toBe('')
    expect(filtersToApi({ ...NO_FILTERS, fit: 'best' }).fit).toBeUndefined()
    expect(activeCount({ ...NO_FILTERS, fit: 'best' })).toBe(0)
  })

  it('exclut « joué » pour la ludothèque, où c’est un onglet', () => {
    const filters = { ...NO_FILTERS, players: 4, played: true }
    expect(filtersToApi(filters).played).toBe(true)
    expect(filtersToApi(filters, { withPlayed: false }).played).toBeUndefined()
    expect(activeCount(filters, { withPlayed: false })).toBe(1)
  })

  it('signalent ce qu’on ignore d’un jeu au lieu de le masquer', () => {
    const filters = { ...NO_FILTERS, players: 4, fit: 'best', maxTime: 60, weight: 'light', coop: true, age: 8 }
    const inconnu = { playingTime: 0, complexity: 0, minAge: null, mechanics: [], bestPlayers: null }
    expect(unknownFields(inconnu, filters)).toEqual(['Durée inconnue', 'Difficulté inconnue', 'Âge inconnu', 'Mode inconnu', 'Idéal inconnu'])
    expect(unknownFields({ ...inconnu, playingTime: 30, complexity: 1.5, minAge: 8, mechanics: ['Dice Rolling'], bestPlayers: [4] }, filters)).toEqual([])
    expect(unknownFields(inconnu, { ...NO_FILTERS, players: 4, fit: 'recommended' })).toEqual(['Conseillé inconnu'])
    expect(unknownFields(inconnu, NO_FILTERS)).toEqual([])
  })

  it('distinguent idéal et conseillé pour le nombre de joueurs choisi', () => {
    const filters = { ...NO_FILTERS, players: 4 }
    expect(fitBadge({ bestPlayers: [4], recommendedPlayers: [3, 4] }, filters)).toBe('Idéal à 4')
    expect(fitBadge({ bestPlayers: [2], recommendedPlayers: [2, 3, 4] }, filters)).toBe('Conseillé à 4')
    expect(fitBadge({ bestPlayers: [2], recommendedPlayers: [2] }, filters)).toBeNull()
    expect(fitBadge({ bestPlayers: null, recommendedPlayers: null }, filters)).toBeNull()
    expect(fitBadge({ bestPlayers: [4] }, NO_FILTERS)).toBeNull()
  })
})
