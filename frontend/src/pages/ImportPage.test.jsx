import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import ImportPage from './ImportPage'

const auth = vi.hoisted(() => ({ user: { email: 'a@example.test', isAdmin: false, bggUsername: 'Perso' } }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: auth.user, login: vi.fn() }) }))
vi.mock('../api', () => ({
  importBgg: vi.fn(), getMe: vi.fn(), analyzeMyLudo: vi.fn(), importMyLudo: vi.fn(), searchGames: vi.fn(),
  getAssociationGames: vi.fn(), removeAssociationGame: vi.fn(),
}))
import { getAssociationGames, importBgg, removeAssociationGame } from '../api'

const renderAt = (url = '/importer') => render(<MemoryRouter initialEntries={[url]}><ImportPage /></MemoryRouter>)

describe('ImportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    auth.user = { email: 'a@example.test', isAdmin: false, bggUsername: 'Perso' }
  })

  it('ouvre l’onglet du compte BGG par défaut, avec trois onglets', () => {
    renderAt()

    expect(screen.getByRole('heading', { level: 1, name: 'Import' })).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map(t => t.textContent)).toEqual(['Compte BGG', 'Ma collection MyLudo', 'Mes parties MyLudo'])
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByLabelText('Pseudo BoardGameGeek')).toBeInTheDocument()
  })

  it('change d’onglet au clic et au clavier', async () => {
    renderAt()

    await userEvent.click(screen.getByRole('tab', { name: 'Ma collection MyLudo' }))
    expect(screen.getByRole('tab', { name: 'Ma collection MyLudo' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByLabelText('Pseudo BoardGameGeek')).not.toBeInTheDocument()

    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Mes parties MyLudo' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Mes parties MyLudo' })).toHaveFocus()

    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
  })

  it('ouvre l’onglet demandé par l’adresse et retombe sur BGG si elle est inconnue', () => {
    const { unmount } = renderAt('/importer?onglet=myludo')
    expect(screen.getByRole('tab', { name: 'Ma collection MyLudo' })).toHaveAttribute('aria-selected', 'true')
    unmount()

    renderAt('/importer?onglet=nimporte-quoi')
    expect(screen.getByRole('tab', { name: 'Compte BGG' })).toHaveAttribute('aria-selected', 'true')
  })

  it('ne propose la ludothèque de l’association qu’aux administrateurs', () => {
    renderAt()

    expect(screen.queryByRole('group', { name: 'Importer dans' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /Ludothèque de l’association/ })).not.toBeInTheDocument()
  })

  describe('administrateur', () => {
    const jeux = { count: 2, games: [{ id: 1, name: 'Azul', yearPublished: 2017, isExpansion: false }, { id: 2, name: 'Dixit', yearPublished: 2008, isExpansion: false }] }

    beforeEach(() => {
      auth.user = { email: 'admin@example.test', isAdmin: true, bggUsername: 'Perso' }
      getAssociationGames.mockResolvedValue(jeux)
    })

    it('choisit la ludothèque de l’association : pseudo vide, panneau du contenu, import ciblé', async () => {
      importBgg.mockResolvedValue({ message: 'ok', total: 3, imported: 1, skipped: 2, enriched: 0, target: 'association' })
      renderAt()
      // Par défaut : sa propre ludothèque, avec son pseudo.
      expect(screen.getByLabelText('Pseudo BoardGameGeek')).toHaveValue('Perso')

      await userEvent.click(screen.getByRole('radio', { name: 'La ludothèque de l’association' }))

      expect(screen.getByLabelText('Pseudo BoardGameGeek de l’association')).toHaveValue('')
      expect(await screen.findByRole('heading', { name: 'Ludothèque de l’association (2 jeux)' })).toBeInTheDocument()

      await userEvent.type(screen.getByLabelText('Pseudo BoardGameGeek de l’association'), 'EnvieDeJouer')
      await userEvent.click(screen.getByRole('button', { name: 'Importer dans la ludothèque de l’association' }))

      expect(importBgg).toHaveBeenCalledWith('EnvieDeJouer', 'association')
      expect(await screen.findByRole('heading', { name: 'Import terminé' })).toBeInTheDocument()
      expect(screen.queryByRole('link', { name: 'Voir ma ludothèque' })).not.toBeInTheDocument()
      expect(getAssociationGames).toHaveBeenCalledTimes(2)
    })

    it('retire un jeu de la ludothèque de l’association', async () => {
      removeAssociationGame.mockResolvedValue({ removed: 1 })
      renderAt('/importer?onglet=myludo')
      await userEvent.click(screen.getByRole('radio', { name: 'La ludothèque de l’association' }))

      await userEvent.click(await screen.findByText('Voir les jeux'))
      await userEvent.click(screen.getByRole('button', { name: 'Retirer Azul' }))

      expect(removeAssociationGame).toHaveBeenCalledWith(1)
      expect(await screen.findByRole('heading', { name: 'Ludothèque de l’association (1 jeu)' })).toBeInTheDocument()
      expect(screen.queryByText('Azul')).not.toBeInTheDocument()
    })

    it('n’affiche pas la cible sur l’onglet des parties', async () => {
      renderAt('/importer?onglet=parties')

      expect(screen.queryByRole('group', { name: 'Importer dans' })).not.toBeInTheDocument()
    })
  })
})
