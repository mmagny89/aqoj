import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import HomePage from './HomePage'
import { clearGameFeedback, getForgottenGems, getHomeRecommendations, getTonightRecommendations, getWishlist, saveTable, setGameFeedback } from '../api'

const auth = vi.hoisted(() => ({ user: null }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: auth.user, loading: false }) }))
vi.mock('../api', () => ({ getHomeRecommendations: vi.fn(), getForgottenGems: vi.fn(), getTonightRecommendations: vi.fn(), saveTable: vi.fn(), setGameFeedback: vi.fn(), clearGameFeedback: vi.fn(), getWishlist: vi.fn() }))

describe('HomePage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    auth.user = null
    getWishlist.mockResolvedValue([])
  })

  it('n’a ni bouton d’envoi ni lien de recherche dans le bandeau : le filtrage est immédiat', () => {
    render(<MemoryRouter><HomePage /></MemoryRouter>)

    expect(screen.queryByRole('button', { name: 'Trouver un jeu' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Chercher par nom/ })).not.toBeInTheDocument()
    expect(saveTable).not.toHaveBeenCalled()
  })

  it('propose les entrées principales quand on n’est pas connecté', () => {
    render(<MemoryRouter><HomePage /></MemoryRouter>)

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/À quoi on joue (ce matin|ce midi|cet après-midi|ce soir|cette nuit)/)
    expect(screen.getByRole('link', { name: /Ma ludothèque/ })).toHaveAttribute('href', '/ludotheque')
  })

  describe('connecté, sans profil', () => {
    const jeu = { id: 1, name: 'Nouveauté Test', yearPublished: 2025, ratingBgg: 7.9, minPlayers: 2, maxPlayers: 4, playingTime: 30, complexity: 2 }
    const froid = { families: [], recent: [{ game: jeu, reason: 'Facile à aborder' }], byDecade: {}, coldStart: true, declared: [], hasHistory: false }

    beforeEach(() => {
      auth.user = { id: 1, email: 'a@example.test' }
      getForgottenGems.mockResolvedValue([])
      getTonightRecommendations.mockResolvedValue({ easy: [], popular: [] })
    })

    it('propose les nouveautés BGG et renvoie au questionnaire du compte', async () => {
      getHomeRecommendations.mockResolvedValue({ ...froid, hasHistory: true })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('tab', { name: 'Nouveautés pour vous' })).toBeInTheDocument()
      expect(screen.getByText(/Dix nouveautés parmi les plus suivies sur BGG/)).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Choisir mes goûts' })).toHaveAttribute('href', '/compte#gouts')
      expect(screen.queryByRole('heading', { name: 'Ce que vous aimez' })).not.toBeInTheDocument()
      expect(screen.queryByText(/Importez votre collection/)).not.toBeInTheDocument()
    })

    it('explique la proposition, la retire sur « Pas intéressé » et la remet sur « Annuler »', async () => {
      getHomeRecommendations.mockResolvedValue({
        ...froid,
        hasHistory: true,
        recent: [{ game: jeu, reason: '', why: { liked: { id: 9, name: 'Catan' }, families: ['deck_building'] } }],
      })
      setGameFeedback.mockResolvedValue({})
      clearGameFeedback.mockResolvedValue(null)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByTestId('why')).toHaveTextContent(/Parce que vous avez aimé Catan, et que vous aimez la mécanique\s*:/)

      await userEvent.click(screen.getByRole('button', { name: /Pas intéressé/ }))
      expect(setGameFeedback).toHaveBeenCalledWith(1, 'not_interested')
      expect(screen.queryByRole('link', { name: 'Nouveauté Test' })).not.toBeInTheDocument()
      expect(screen.getByText(/« Nouveauté Test » ne vous sera plus proposé/)).toBeInTheDocument()

      await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))
      expect(clearGameFeedback).toHaveBeenCalledWith(1)
      expect(await screen.findByRole('link', { name: 'Nouveauté Test' })).toBeInTheDocument()
    })

    it('range les envies de jeu dans leur onglet, et un jeu marqué « J’aimerais jouer » y arrive aussitôt', async () => {
      const envie = { id: 7, name: 'Jeu Convoité', yearPublished: 2020, ratingBgg: 7.5, minPlayers: 2, maxPlayers: 4, playingTime: 30, complexity: 2 }
      getWishlist.mockResolvedValue([{ game: envie, why: null, feedback: 'want_to_play' }])
      getHomeRecommendations.mockResolvedValue({ ...froid, hasHistory: true })
      setGameFeedback.mockResolvedValue({})
      clearGameFeedback.mockResolvedValue(null)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      const onglet = await screen.findByRole('tab', { name: 'Mes envies de jeu (1)' })
      expect(onglet).toHaveAttribute('aria-selected', 'true')
      expect(screen.getByRole('link', { name: 'Jeu Convoité' })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /J’aimerais jouer : Jeu Convoité/ })).toHaveAttribute('aria-pressed', 'true')

      // Retirer l'envie vide la liste ; l'onglet disparaît.
      await userEvent.click(screen.getByRole('button', { name: /J’aimerais jouer : Jeu Convoité/ }))
      expect(clearGameFeedback).toHaveBeenCalledWith(7)
      expect(screen.queryByRole('tab', { name: /Mes envies de jeu/ })).not.toBeInTheDocument()

      // Marquer une nouveauté l'ajoute à la liste sans rechargement.
      await userEvent.click(screen.getByRole('button', { name: /J’aimerais jouer : Nouveauté Test/ }))
      expect(setGameFeedback).toHaveBeenCalledWith(1, 'want_to_play')
      expect(await screen.findByRole('tab', { name: 'Mes envies de jeu (1)' })).toBeInTheDocument()
    })

    it('invite à renseigner BGG, MyLudo ou des parties tant qu’il n’y a aucun historique, et se masque une semaine', async () => {
      getHomeRecommendations.mockResolvedValue(froid)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('link', { name: /^Renseigner mon compte BGG/ })).toHaveAttribute('href', '/importer?onglet=bgg')
      expect(screen.getByRole('link', { name: /^Importer ma ludothèque MyLudo/ })).toHaveAttribute('href', '/importer?onglet=myludo')
      expect(screen.getByRole('link', { name: /^Enregistrer des parties/ })).toHaveAttribute('href', '/parties')
      expect(screen.getByRole('link', { name: 'Ou choisir mes goûts' })).toHaveAttribute('href', '/compte#gouts')
      expect(screen.queryByRole('link', { name: 'Choisir mes goûts' })).not.toBeInTheDocument()

      await userEvent.click(screen.getByRole('button', { name: 'Plus tard' }))

      expect(screen.queryByRole('link', { name: /^Renseigner mon compte BGG/ })).not.toBeInTheDocument()
    })

    it('n’invite pas à fournir un historique quand il y en a un', async () => {
      getHomeRecommendations.mockResolvedValue({ ...froid, hasHistory: true })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await screen.findByRole('tab', { name: 'Nouveautés pour vous' })
      expect(screen.queryByRole('link', { name: /^Renseigner mon compte BGG/ })).not.toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Choisir mes goûts' })).toBeInTheDocument()
    })

    it('cherche dans la ludothèque par défaut, retient le choix « Tout le catalogue » et redemande les idées', async () => {
      getHomeRecommendations.mockResolvedValue(froid)
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      const ludotheque = await screen.findByRole('button', { name: 'Ludothèque de l’asso et des membres' })
      expect(ludotheque).toHaveAttribute('aria-pressed', 'true')
      expect(getHomeRecommendations).toHaveBeenLastCalledWith({ scope: 'library' })

      await userEvent.click(screen.getByRole('button', { name: 'Tout le catalogue' }))

      await waitFor(() => expect(getHomeRecommendations).toHaveBeenLastCalledWith({ scope: 'catalogue' }))
      await waitFor(() => expect(getTonightRecommendations).toHaveBeenLastCalledWith(expect.objectContaining({ scope: 'catalogue' })))
      expect(localStorage.getItem('aqoj_perimetre')).toBe('catalogue')
    })

    it('regroupe nouveautés, oubliés et époques en onglets', async () => {
      const ancien = { ...jeu, id: 2, name: 'Jeu Oublié' }
      const classique = { ...jeu, id: 3, name: 'Jeu Classique' }
      getHomeRecommendations.mockResolvedValue({ ...froid, coldStart: false, families: ['cooperation'], declared: ['cooperation'], byDecade: { Classiques: [{ game: classique, reason: 'Culte' }] } })
      getForgottenGems.mockResolvedValue([{ game: ancien, reason: 'Pas joué depuis un an' }])
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('tab', { name: 'Nouveautés pour vous' })).toHaveAttribute('aria-selected', 'true')
      expect(screen.queryByText('Jeu Oublié')).not.toBeInTheDocument()

      await userEvent.click(await screen.findByRole('tab', { name: 'Oubliés de la ludothèque' }))
      expect(screen.getByText('Jeu Oublié')).toBeInTheDocument()

      await userEvent.click(screen.getByRole('tab', { name: 'Par époque' }))
      expect(screen.getByText('Jeu Classique')).toBeInTheDocument()
      expect(getForgottenGems).toHaveBeenLastCalledWith({ scope: 'library' })

      // Dans tout le catalogue, « oubliés » n'a pas de sens : l'onglet disparaît, les autres restent.
      await userEvent.click(screen.getByRole('button', { name: 'Tout le catalogue' }))
      await waitFor(() => expect(screen.queryByRole('tab', { name: 'Oubliés de la ludothèque' })).not.toBeInTheDocument())
      expect(await screen.findByRole('tab', { name: 'Par époque' })).toBeInTheDocument()
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

    it('retrouve la table enregistrée et la propose pour les idées du soir', async () => {
      getHomeRecommendations.mockResolvedValue({ ...reco, table: { players: 6, maxTime: 90, scope: 'library', difficulty: null } })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await waitFor(() => expect(getTonightRecommendations).toHaveBeenCalled())
      expect(screen.getByRole('button', { name: '6' })).toHaveAttribute('aria-pressed', 'true')
      expect(screen.getByRole('button', { name: '≤ 1 h 30' })).toHaveAttribute('aria-pressed', 'true')
      expect(getTonightRecommendations).toHaveBeenCalledTimes(1)
      expect(getTonightRecommendations).toHaveBeenCalledWith({ players: 6, maxTime: 90, scope: 'library', difficulty: null })
    })

    it('enregistre chaque changement de table et le dit', async () => {
      saveTable.mockResolvedValue({})
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await waitFor(() => expect(getTonightRecommendations).toHaveBeenCalled())

      await userEvent.click(screen.getByRole('button', { name: '8' }))
      expect(saveTable).toHaveBeenLastCalledWith({ players: 8, maxTime: 60 })
      expect(await screen.findByText(/Table enregistrée/)).toBeInTheDocument()

      await userEvent.click(screen.getByRole('button', { name: '≤ 15 min' }))
      expect(saveTable).toHaveBeenLastCalledWith({ players: 8, maxTime: 15 })
    })

    it('prévient quand l’enregistrement échoue', async () => {
      saveTable.mockRejectedValue(new Error('boom'))
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await waitFor(() => expect(getTonightRecommendations).toHaveBeenCalled())

      await userEvent.click(screen.getByRole('button', { name: '2' }))

      expect(await screen.findByText(/Impossible d’enregistrer la table/)).toBeInTheDocument()
    })

    it('affiche ce que jouent les autres membres et les jeux faciles pour la table choisie', async () => {
      getTonightRecommendations.mockResolvedValue({
        popular: [{ game: jeu(1, 'Jeu Partagé'), reason: 'Joué par 3 membres cette année' }],
        easy: [{ game: jeu(2, 'Jeu Facile'), reason: 'Facile à aborder' }],
      })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('heading', { name: 'Pour votre table' })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Ce que jouent les autres membres' })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Faciles à expliquer' })).toBeInTheDocument()
      expect(getTonightRecommendations).toHaveBeenCalledWith({ players: 4, maxTime: 60, scope: 'library', difficulty: null })
    })

    it('redemande les idées quand on change la table, et ne filtre plus la durée avec « Peu importe »', async () => {
      getTonightRecommendations.mockResolvedValue({ popular: [], easy: [{ game: jeu(2, 'Jeu Facile'), reason: 'Facile à aborder' }] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await screen.findByRole('heading', { name: 'Pour votre table' })

      await userEvent.click(screen.getByRole('button', { name: '2' }))
      await userEvent.click(screen.getByRole('button', { name: 'Peu importe' }))

      expect(getTonightRecommendations).toHaveBeenLastCalledWith({ players: 2, maxTime: null, scope: 'library', difficulty: null })
      expect(await screen.findByText('2 joueurs')).toBeInTheDocument()
    })

    it('filtre les idées selon la difficulté choisie', async () => {
      getTonightRecommendations.mockResolvedValue({ popular: [], easy: [{ game: jeu(2, 'Jeu Costaud'), reason: '' }] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await screen.findByRole('heading', { name: 'Pour votre table' })

      await userEvent.click(screen.getByRole('button', { name: 'Costaud' }))

      expect(getTonightRecommendations).toHaveBeenLastCalledWith({ players: 4, maxTime: 60, scope: 'library', difficulty: 'heavy' })
      expect(await screen.findByRole('heading', { name: 'Pour les costauds' })).toBeInTheDocument()
    })

    it('n’affiche un jeu qu’une fois, même s’il est à la fois récent et facile', async () => {
      const partage = jeu(1, 'Jeu Doublon')
      getHomeRecommendations.mockResolvedValue({ ...reco, recent: [{ game: partage, reason: 'Nouveauté' }] })
      getTonightRecommendations.mockResolvedValue({ popular: [], easy: [{ game: partage, reason: 'Facile à aborder' }] })
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await screen.findByRole('tab', { name: 'Nouveautés pour vous' })
      expect(screen.getAllByText('Jeu Doublon')).toHaveLength(1)
      expect(screen.queryByRole('heading', { name: 'Pour votre table' })).not.toBeInTheDocument()
    })

    it('invite à renseigner ses goûts quand le profil vient de l’historique, et se tait une semaine après « Plus tard »', async () => {
      getHomeRecommendations.mockResolvedValue({ ...reco, declared: [], families: ['auction'] })
      const { unmount } = render(<MemoryRouter><HomePage /></MemoryRouter>)

      expect(await screen.findByRole('link', { name: 'Choisir mes goûts' })).toHaveAttribute('href', '/compte#gouts')
      expect(screen.queryByRole('heading', { name: 'Ce que vous aimez' })).not.toBeInTheDocument()

      await userEvent.click(screen.getByRole('button', { name: 'Plus tard' }))
      expect(screen.queryByRole('link', { name: 'Choisir mes goûts' })).not.toBeInTheDocument()

      unmount()
      render(<MemoryRouter><HomePage /></MemoryRouter>)
      await waitFor(() => expect(getHomeRecommendations).toHaveBeenCalledTimes(2))
      expect(screen.queryByRole('link', { name: 'Choisir mes goûts' })).not.toBeInTheDocument()
    })

    it('n’invite pas quand les goûts sont déjà renseignés', async () => {
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await waitFor(() => expect(getTonightRecommendations).toHaveBeenCalled())
      expect(screen.queryByRole('link', { name: 'Choisir mes goûts' })).not.toBeInTheDocument()
    })

    it('masque la section quand il n’y a rien à proposer', async () => {
      render(<MemoryRouter><HomePage /></MemoryRouter>)

      await waitFor(() => expect(getTonightRecommendations).toHaveBeenCalled())
      expect(screen.queryByRole('heading', { name: 'Pour votre table' })).not.toBeInTheDocument()
    })
  })
})
