import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import RecommendationPage from './RecommendationPage'
import { getRecommendations, getThemeMappings, getUserPreferences } from '../api'

const auth = vi.hoisted(() => ({ value: { user: { id: 1 }, loading: false } }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => auth.value }))
vi.mock('../api', () => ({ getRecommendations: vi.fn(), getUserPreferences: vi.fn(), getThemeMappings: vi.fn() }))

const jeu = (id, name) => ({ game: { id, name, yearPublished: 2024, ratingBgg: 7.5, minPlayers: 2, maxPlayers: 4, playingTime: 30, complexity: 2, isExpansion: false }, reason: 'Pour vous' })

describe('RecommendationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getThemeMappings.mockResolvedValue([])
    getUserPreferences.mockResolvedValue({ table: { players: 5, maxTime: 90 }, declaredFamilies: ['cooperation'], topEngines: [] })
    getRecommendations.mockImplementation(async ({ scope }) => {
      const items = ({
        collection: [jeu(1, 'Jeu Perso')],
        library: [jeu(2, 'Jeu Asso'), jeu(3, 'Jeu Voisin')],
        discover: [jeu(4, 'Jeu Catalogue')],
      })[scope]
      return { items, total: items.length, totals: { base: items.length, expansion: 0 }, page: 1 }
    })
  })

  it('part tout seul de la table et des styles favoris, sans bouton de recherche', async () => {
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('À quoi on joue…')
    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(3))
    expect(getRecommendations).toHaveBeenCalledWith({ players: 5, maxTime: 90, families: ['cooperation'], categories: [], scope: 'library' })
    expect(screen.queryByRole('button', { name: /Trouver des jeux/ })).not.toBeInTheDocument()
    expect(await screen.findByText('Jeu Perso')).toBeInTheDocument()
  })

  it('propose trois onglets avec leur compte et change de liste', async () => {
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)

    await screen.findByText('Jeu Perso')
    expect(screen.getByRole('tab', { name: /Dans ma ludothèque/ })).toHaveTextContent('1')
    expect(screen.getByRole('tab', { name: /À l’association/ })).toHaveTextContent('2')
    expect(screen.getByRole('tab', { name: /Tout le catalogue/ })).toHaveTextContent('1')

    await userEvent.click(screen.getByRole('tab', { name: /À l’association/ }))
    expect(screen.getByText('Jeu Asso')).toBeInTheDocument()
    expect(screen.queryByText('Jeu Perso')).not.toBeInTheDocument()
  })

  it('actualise les résultats dès qu’un critère change', async () => {
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)
    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(3))

    // Les joueurs se règlent à la jauge (cran 0 = tous, puis 1 à 8 joueurs).
    fireEvent.change(screen.getByRole('slider', { name: 'Nombre de joueurs' }), { target: { value: '2' } })

    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(6))
    expect(getRecommendations).toHaveBeenLastCalledWith(expect.objectContaining({ players: 2, maxTime: 90 }))
  })

  it('relance les résultats avec la difficulté choisie dans la barre de table', async () => {
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)
    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(3))

    // La facette « Difficulté » est fermée tant qu'aucune difficulté n'est retenue ; joueurs et durée de la table enregistrée sont déjà posés.
    await userEvent.click(screen.getByRole('button', { name: /^Difficulté/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Costaud' }))

    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(6))
    expect(getRecommendations).toHaveBeenLastCalledWith(expect.objectContaining({ weight: 'heavy', players: 5, maxTime: 90 }))
  })

  it('reprend les filtres de contexte de l’adresse, sans écraser par la table enregistrée', async () => {
    render(<MemoryRouter initialEntries={['/recommander?joueurs=3&nombre=ideal&poids=leger&mode=coop']}><RecommendationPage /></MemoryRouter>)

    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(3))
    expect(getRecommendations).toHaveBeenCalledWith(expect.objectContaining({ players: 3, fit: 'best', weight: 'light', coop: true, scope: 'collection' }))
    expect(screen.getByRole('button', { name: 'Idéal' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('charge la page suivante avec « Voir plus » sans perdre les premiers résultats', async () => {
    getRecommendations.mockImplementation(async ({ scope, page = 1, type }) => {
      const totals = { base: 2, expansion: 1 }
      if (scope !== 'collection') return { items: [], total: 0, totals: { base: 0, expansion: 0 }, page: 1 }
      if (type === 'expansion') return { items: [{ game: { ...jeu(9, 'Extension Un').game, isExpansion: true }, reason: '' }], total: 1, totals, page: 1 }
      return page === 1
        ? { items: [jeu(1, 'Jeu Page Un')], total: 2, totals, page: 1 }
        : { items: [jeu(2, 'Jeu Page Deux')], total: 2, totals, page: 2 }
    })
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)
    await screen.findByText('Jeu Page Un')

    await userEvent.click(screen.getByRole('button', { name: /Voir plus de jeux \(1 restants\)/ }))

    expect(await screen.findByText('Jeu Page Deux')).toBeInTheDocument()
    expect(screen.getByText('Jeu Page Un')).toBeInTheDocument()
    expect(getRecommendations).toHaveBeenLastCalledWith(expect.objectContaining({ scope: 'collection', page: 2 }))
    expect(screen.queryByRole('button', { name: /Voir plus/ })).not.toBeInTheDocument()
  })

  it('charge les extensions à part, avec leur propre pagination', async () => {
    const totals = { base: 1, expansion: 1 }
    getRecommendations.mockImplementation(async ({ scope, type }) => {
      if (scope !== 'collection') return { items: [], total: 0, totals: { base: 0, expansion: 0 }, page: 1 }
      return type === 'expansion'
        ? { items: [{ game: { ...jeu(9, 'Extension Un').game, isExpansion: true }, reason: '' }], total: 1, totals, page: 1 }
        : { items: [jeu(1, 'Jeu Base Un')], total: 1, totals, page: 1 }
    })
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)
    await screen.findByText('Jeu Base Un')

    await userEvent.click(screen.getByRole('button', { name: /^Extensions/ }))

    expect(await screen.findByText('Extension Un')).toBeInTheDocument()
    expect(screen.queryByText('Jeu Base Un')).not.toBeInTheDocument()
    expect(getRecommendations).toHaveBeenLastCalledWith(expect.objectContaining({ scope: 'collection', type: 'expansion' }))
  })

  it('ouvre les facettes qui ont un élément retenu et ferme les autres', async () => {
    render(<MemoryRouter><RecommendationPage /></MemoryRouter>)
    await waitFor(() => expect(getRecommendations).toHaveBeenCalledTimes(3))

    const facette = (nom) => screen.getByRole('button', { name: new RegExp(`^${nom}`) })
    expect(facette('Nombre de joueurs')).toHaveAttribute('aria-expanded', 'true') // joueurs et durée viennent de la table enregistrée
    expect(facette('Difficulté')).toHaveAttribute('aria-expanded', 'false')
    expect(facette('Mécaniques')).toHaveAttribute('aria-expanded', 'true') // « Coopération » est présélectionnée
    expect(facette('Style / Moteur')).toHaveAttribute('aria-expanded', 'false')

    await userEvent.click(facette('Style / Moteur'))
    expect(facette('Style / Moteur')).toHaveAttribute('aria-expanded', 'true')

    // Scénario et « joué » restent en haut de la liste, hors de la barre latérale.
    expect(screen.getByRole('group', { name: 'Scénario' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Joué' })).toBeInTheDocument()
  })
})
