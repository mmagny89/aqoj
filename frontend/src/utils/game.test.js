import { describe, expect, it } from 'vitest'
import { frenchEditionLabel, gameImage } from './game'

describe('gameImage', () => {
  it('préfère la boîte de l’édition française, puis l’image d’origine', () => {
    expect(gameImage({ imageUrl: 'origine.jpg', editionFrImageUrl: 'boite-fr.jpg' })).toBe('boite-fr.jpg')
    expect(gameImage({ imageUrl: 'origine.jpg', editionFrImageUrl: null })).toBe('origine.jpg')
    expect(gameImage({})).toBeNull()
  })
})

describe('frenchEditionLabel', () => {
  it('donne l’éditeur et l’année connus, ou rien', () => {
    expect(frenchEditionLabel({ editionFrPublisher: 'Filosofia', editionFrYear: 2021 })).toBe('Filosofia · 2021')
    expect(frenchEditionLabel({ editionFrYear: 2021 })).toBe('2021')
    expect(frenchEditionLabel({ editionFrPublisher: 'Filosofia' })).toBe('Filosofia')
    expect(frenchEditionLabel({})).toBeNull()
  })
})
