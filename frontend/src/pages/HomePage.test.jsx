import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import HomePage from './HomePage'
import { getForgottenGems, getHomeRecommendations, getTonightRecommendations, saveTaste } from '../api'

const auth = vi.hoisted(() => ({ user: null }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: auth.user, loading: false }) }))
vi.mock('../api', () => ({ getHomeRecommendations: vi.fn(), getForgottenGems: vi.fn(), getTonightRecommendations: vi.fn(), saveTaste: vi.fn() }))

function Where() {
  const location = useLocation()
  return <p>route : {location.pathname}{location.search}</p>
}

describe('HomePage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    auth.user = null
  })

  it('envoie la table et la durée choisies à la page de recommandation', async () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/recommander" element={<Where />} />
        </Routes>
      </MemoryRouter>,
    )

    await userEvent.click(screen.getByRole('button', { name: '2' }))
    await userEvent.click(screen.getByRole('button', { name: '≤ 2 h' }))
    await userEvent.click(screen.getByRole('button', { name: 'Trouver un jeu' }))

    expect(screen.getByText('route : /recommander?joueurs=2&duree=120')).toBeInTheDocument()
  })

  it('propose les entrées principales quand on n’est pas connecté', () => {
    render(<MemoryRouter><HomePage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent("Qu'est-ce qu'on joue ce soir")
    expect(screen.getByRole('link', { name: /Ma ludothèque/ })).toHaveAttribute('href', '/ludotheque')
  })

  describe('connecté, sans profil', () => {
    const jeu = { id: 1, name: 'Nouveauté Test', yearPublished: 2025, ratingBgg: 7.9, minPlayers: 2, maxPlayers: 4, playingTime: 30, complexity: 2 }
    const froid = { families: [], recent: [{ game: jeu, reason: 'Nouveauté 2025 · 7.9/10 sur BGG · facile à aborder' }], byDecade: {}, coldStart: true, declared: [] }

    beforeEach(() => {
      auth.user = { id: 1, email: 'a@example.test' }
      getForgottenGems.mockResolvedValue([])
      getTonightRecommendations.mockResolvedValue({ easy: [], popular: [] })
    })

    it('propose des nouveautés accessibles et le questionnaire, sans parler de BGG', async () => {
      getHomeRecommendations.mockResolvedValue(froid)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('heading', { name: 'Nouveautés à découvrir' })).toBeInTheDocument()
      expect(screen.getByText(/Nous ne connaissons pas encore vos goûts/)).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Ce que vous aimez' })).toBeInTheDocument()
      expect(screen.queryByText(/Importez votre collection/)).not.toBeInTheDocument()
    })

    it('enregistre les goûts choisis puis recharge les suggestions', async () => {
      getHomeRecommendations.mockResolvedValueOnce(froid).mockResolvedValue({ ...froid, coldStart: false, families: ['cooperation'], declared: ['cooperation'] })
      saveTaste.mockResolvedValue({ declaredFamilies: ['cooperation'] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      const enregistrer = await screen.findByRole('button', { name: 'Enregistrer mes goûts' })
      expect(enregistrer).toBeDisabled()
      await userEvent.click(screen.getByRole('button', { name: 'Coopération' }))
      await userEvent.click(enregistrer)

      expect(saveTaste).toHaveBeenCalledWith(['cooperation'])
      expect(await screen.findByRole('heading', { name: 'Nouveautés pour vous' })).toBeInTheDocument()
      expect(getHomeRecommendations).toHaveBeenCalledTimes(2)
    })

    it('limite le questionnaire à six goûts', async () => {
      getHomeRecommendations.mockResolvedValue(froid)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await screen.findByRole('heading', { name: 'Ce que vous aimez' })
      for (const nom of ['Placement d\'Ouvriers', 'Construction de Deck', 'Construction de Moteur', 'Contrôle de Zone', 'Gestion de Main', 'Enchères']) {
        await userEvent.click(screen.getByRole('button', { name: nom }))
      }

      expect(screen.getByRole('button', { name: 'Coopération' })).toBeDisabled()
      expect(screen.getByRole('button', { name: 'Enchères' })).toBeEnabled()
    })
  })

  describe('connecté : pour votre table', () => {
    const jeu = (id, name) => ({ id, name, yearPublished: 2024, ratingBgg: 7.5, minPlayers: 2, maxPlayers: 4, playingTime: 30, complexity: 2 })
    const reco = { families: ['cooperation'], recent: [], byDecade: {}, coldStart: false, declared: ['cooperation'] }

    beforeEach(() => {
      auth.user = { id: 1, email: 'a@example.test' }
      getForgottenGems.mockResolvedValue([])
      getHomeRecommendations.mockResolvedValue(reco)
      getTonightRecommendations.mockResolvedValue({ easy: [], popular: [] })
    })

    it('affiche ce que jouent les autres membres et les jeux faciles pour la table choisie', async () => {
      getTonightRecommendations.mockResolvedValue({
        popular: [{ game: jeu(1, 'Jeu Partagé'), reason: 'Joué par 3 membres cette année' }],
        easy: [{ game: jeu(2, 'Jeu Facile'), reason: 'Facile à aborder · 7.5/10 sur BGG' }],
      })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('heading', { name: 'Pour votre table' })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Ce que jouent les autres membres' })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Faciles à expliquer' })).toBeInTheDocument()
      expect(screen.getByText('Joué par 3 membres cette année')).toBeInTheDocument()
      expect(getTonightRecommendations).toHaveBeenCalledWith({ players: 4, maxTime: 60 })
    })

    it('redemande les idées quand on change la table, et ne filtre plus la durée avec « Peu importe »', async () => {
      getTonightRecommendations.mockResolvedValue({ popular: [], easy: [{ game: jeu(2, 'Jeu Facile'), reason: 'Facile à aborder' }] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await screen.findByRole('heading', { name: 'Pour votre table' })

      await userEvent.click(screen.getByRole('button', { name: '2' }))
      await userEvent.click(screen.getByRole('button', { name: 'Peu importe' }))

      expect(getTonightRecommendations).toHaveBeenLastCalledWith({ players: 2, maxTime: null })
      expect(await screen.findByText(/Pour 2 joueurs/)).toBeInTheDocument()
    })

    it('n’affiche un jeu qu’une fois, même s’il est à la fois récent et facile', async () => {
      const partage = jeu(1, 'Jeu Doublon')
      getHomeRecommendations.mockResolvedValue({ ...reco, recent: [{ game: partage, reason: 'Nouveauté' }] })
      getTonightRecommendations.mockResolvedValue({ popular: [], easy: [{ game: partage, reason: 'Facile à aborder' }] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await screen.findByRole('heading', { name: 'Nouveautés pour vous' })
      expect(screen.getAllByText('Jeu Doublon')).toHaveLength(1)
      expect(screen.queryByRole('heading', { name: 'Pour votre table' })).not.toBeInTheDocument()
    })

    it('masque la section quand il n’y a rien à proposer', async () => {
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await screen.findByRole('heading', { name: 'Ce que vous aimez' })
      expect(screen.queryByRole('heading', { name: 'Pour votre table' })).not.toBeInTheDocument()
    })
  })
})
