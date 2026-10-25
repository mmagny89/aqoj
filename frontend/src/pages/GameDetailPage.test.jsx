import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import GameDetailPage from './GameDetailPage'

vi.mock('../api', () => ({ getGame: vi.fn(), getSimilarGames: vi.fn(), createSession: vi.fn() }))
import { getGame, getSimilarGames } from '../api'

const EN = 'Build a palace and score points with your tiles. '.repeat(10).trim()
const FR = 'Construisez un palais et marquez des points avec vos tuiles. '.repeat(10).trim()

const base = {
  id: 7, bggId: '230802', name: 'Azul', nameOriginal: 'Azul', minPlayers: 2, maxPlayers: 4, playingTime: 45, complexity: 1.8,
  yearPublished: 2017, publisherOriginal: 'Plan B Games', editionFrPublisher: 'Next Move Games', editionFrYear: 2018,
  editionFrImageUrl: 'https://cf.geekdo-images.com/boite-fr.jpg', imageUrl: 'https://cf.geekdo-images.com/origine.jpg',
  description: EN, descriptionFr: null, descriptionFrPending: false,
  mechanicsByFamily: {}, primaryFamilies: [], expansions: [], baseGames: [], siblingExpansions: [], categories: [], mechanics: [],
}

const renderPage = (game) => {
  getGame.mockResolvedValue({ ...base, ...game })
  getSimilarGames.mockResolvedValue({ results: [], hasMore: false })
  return render(
    <MemoryRouter initialEntries={['/games/7']}>
      <Routes><Route path="/games/:id" element={<GameDetailPage />} /></Routes>
    </MemoryRouter>,
  )
}

describe('GameDetailPage : description et éditions', () => {
  beforeEach(() => vi.clearAllMocks())

  it('affiche la traduction automatique en premier, avec un accès à l’original anglais', async () => {
    renderPage({ descriptionFr: FR })

    expect(await screen.findByText('Traduction automatique')).toBeInTheDocument()
    expect(screen.getByText(FR)).toHaveAttribute('lang', 'fr')
    expect(screen.queryByText(EN)).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Lire l’original (anglais)' }))

    expect(screen.getByText(EN)).toHaveAttribute('lang', 'en')
    expect(screen.queryByText('Traduction automatique')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Lire la traduction française' }))
    expect(screen.getByText(FR)).toBeInTheDocument()
  })

  it('garde l’anglais et le dit quand la traduction se prépare', async () => {
    renderPage({ descriptionFrPending: true })

    expect(await screen.findByText(EN)).toHaveAttribute('lang', 'en')
    expect(screen.getByRole('status')).toHaveTextContent('traduction française est en préparation')
    expect(screen.queryByRole('button', { name: /original|traduction française/ })).not.toBeInTheDocument()
  })

  it('n’ajoute rien quand la description anglaise n’a pas de traduction à attendre', async () => {
    renderPage({})

    expect(await screen.findByText(EN)).toBeInTheDocument()
    expect(screen.queryByText('Traduction automatique')).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('montre l’édition d’origine, l’édition française et la boîte française', async () => {
    renderPage({})

    expect(await screen.findByText('Plan B Games · 2017')).toBeInTheDocument()
    expect(screen.getByText('Next Move Games · 2018')).toBeInTheDocument()
    expect(document.querySelector('img[src="https://cf.geekdo-images.com/boite-fr.jpg"]')).not.toBeNull()
  })
})

describe('GameDetailPage : extensions et fiche technique', () => {
  beforeEach(() => vi.clearAllMocks())

  const ext = (id, name, extra) => ({ id, name, isExpansion: true, yearPublished: 2020, ratingBgg: 7, usersRated: 500, owned: false, inAssociation: false, withMember: false, ...extra })

  it('dit pour chaque extension si on l’a, si l’association l’a ou si un membre peut la prêter', async () => {
    renderPage({ expansions: [
      ext(11, 'Ext Perso', { owned: true }),
      ext(12, 'Ext Asso', { inAssociation: true }),
      ext(13, 'Ext Membre', { withMember: true }),
      ext(14, 'Ext Rien', {}),
    ] })

    expect(await screen.findByRole('heading', { name: /Extensions/ })).toBeInTheDocument()
    expect(screen.getByText('✓ Vous l’avez')).toBeInTheDocument()
    expect(screen.getByText('À l’association')).toBeInTheDocument()
    expect(screen.getByText('Chez un membre')).toBeInTheDocument()
    expect(screen.getByText('Ext Rien')).toBeInTheDocument()
  })

  it('range le technique dans une fiche compacte, avec le détail BGG replié', async () => {
    renderPage({
      primaryFamilies: [{ family: 'worker_placement', isEngine: true }],
      mechanicFamilies: ['worker_placement', 'randomness'],
      mechanicsByFamily: { worker_placement: ['Worker Placement'], randomness: ['Dice Rolling'] },
      mechanics: ['Worker Placement', 'Dice Rolling', 'Hand Management'],
    })

    expect(await screen.findByRole('heading', { name: 'Fiche technique' })).toBeInTheDocument()
    const detail = screen.getByText('Détail des mécaniques BGG').closest('details')
    expect(detail).not.toHaveAttribute('open')
    expect(detail).toHaveTextContent('Hand Management')
    expect(screen.queryByText('Mécaniques BGG (brut)')).not.toBeInTheDocument()
  })
})
