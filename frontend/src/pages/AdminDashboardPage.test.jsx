import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminDashboardPage from './AdminDashboardPage'

vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: { isAdmin: true } }) }))
vi.mock('../components/AdminCatalogue', () => ({
  JobsPanel: () => <p>Panneau des tâches</p>,
  GamesPanel: ({ filter }) => <p>Explorateur sur {filter}</p>,
}))
vi.mock('../components/AdminMembers', () => ({ default: () => <p>Panneau des membres</p> }))
vi.mock('../api', () => ({ getAdminStats: vi.fn() }))
import { getAdminStats } from '../api'

const stats = {
  generated_at: '2026-10-25T12:00:00+00:00',
  games: { total: 10, enriched: 4, pending: 6, stale_count: 2, no_mechanics: 1, no_image: 1, no_description: 1 },
  expansions: { total: 3, enriched: 1, pending: 2, stale_count: 0, linked: 1, unlinked: 2, no_mechanics: 0, no_image: 0 },
  users: { total: 5, with_bgg: 2, without_bgg: 3, admins: 1, collections: { total_links: 4, rated_links: 1, avg_per_user: 2, max_per_user: 3 } },
  sessions: { total: 1, this_month: 1, this_week: 1, this_year: 1, distinct_games: 1, distinct_users: 1, with_rating: 0, last_session: null },
  mappings: { total_mappings: 1, families_used: 1, unmapped_bgg_mechanics: 0 },
  themes: { total_mappings: 1, distinct_labels: 1, distinct_groups: 1, unmapped_bgg_categories: 0 },
}

const renderAt = (url) => render(<MemoryRouter initialEntries={[url]}><AdminDashboardPage /></MemoryRouter>)

describe('AdminDashboardPage', () => {
  beforeEach(() => {
    getAdminStats.mockResolvedValue(stats)
  })

  it('ouvre le catalogue par défaut et change d’onglet', async () => {
    renderAt('/admin')

    expect(await screen.findByText('Catalogue de jeux (jeux de base)')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Catalogue' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByText('Panneau des tâches')).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('tab', { name: 'Tâches' }))

    expect(screen.getByText('Panneau des tâches')).toBeInTheDocument()
    expect(screen.queryByText('Catalogue de jeux (jeux de base)')).not.toBeInTheDocument()
  })

  it('ouvre l’onglet Membres, sans le contenu des autres', async () => {
    renderAt('/admin?onglet=membres')

    expect(await screen.findByText('Panneau des membres')).toBeInTheDocument()
    expect(screen.queryByText('Utilisateurs')).not.toBeInTheDocument()
  })

  it('lit l’onglet dans l’URL', async () => {
    renderAt('/admin?onglet=communaute')

    expect(await screen.findByText('Utilisateurs')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Communauté' })).toHaveAttribute('aria-selected', 'true')
  })

  it('une carte du catalogue ouvre l’explorateur sur son filtre', async () => {
    renderAt('/admin')

    await userEvent.click(await screen.findByRole('button', { name: /Stubs CSV/ }))

    expect(screen.getByText('Explorateur sur pending')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Explorer' })).toHaveAttribute('aria-selected', 'true')
  })

  it('se parcourt aux flèches du clavier', async () => {
    renderAt('/admin')
    await screen.findByText('Catalogue de jeux (jeux de base)')

    screen.getByRole('tab', { name: 'Catalogue' }).focus()
    await userEvent.keyboard('{ArrowRight}')

    expect(screen.getByRole('tab', { name: 'Tâches' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Tâches' })).toHaveFocus()
  })
})
