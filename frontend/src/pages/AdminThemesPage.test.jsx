import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminThemesPage from './AdminThemesPage'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { isAdmin: true } }) }))
vi.mock('../api', () => ({
  getThemeMappings: vi.fn(),
  getUnmappedBggCategories: vi.fn(),
  createThemeMapping: vi.fn(),
  updateThemeMapping: vi.fn(),
  deleteThemeMapping: vi.fn(),
}))
import { createThemeMapping, getThemeMappings, getUnmappedBggCategories } from '../api'

describe('AdminThemesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getThemeMappings.mockResolvedValue([{ id: 1, bggCategory: 'Science Fiction', themeGroup: 'universe', themeLabel: 'Science-fiction', themeEmoji: '🚀' }])
    getUnmappedBggCategories.mockResolvedValue([{ bgg_category: 'Trains', nb: 120 }])
    createThemeMapping.mockResolvedValue({})
  })

  it('liste les mappings du groupe et filtre par texte', async () => {
    render(<MemoryRouter><AdminThemesPage /></MemoryRouter>)

    expect(await screen.findByRole('rowheader', { name: 'Science Fiction' })).toBeInTheDocument()
    await userEvent.type(screen.getByRole('searchbox', { name: 'Filtrer' }), 'zzz')

    expect(screen.getByText('Aucun résultat.')).toBeInTheDocument()
  })

  it('mappe une catégorie non mappée depuis une fenêtre fermable à Échap', async () => {
    render(<MemoryRouter><AdminThemesPage /></MemoryRouter>)

    await userEvent.click(await screen.findByRole('tab', { name: /Non mappées/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Mapper' }))

    const dialog = screen.getByRole('dialog', { name: 'Ajouter un mapping' })
    await userEvent.type(within(dialog).getByLabelText('Label français'), 'Trains')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    expect(createThemeMapping).toHaveBeenCalledWith(expect.objectContaining({ bggCategory: 'Trains', themeLabel: 'Trains' }))

    await userEvent.click(await screen.findByRole('tab', { name: /Non mappées/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Mapper' }))
    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
