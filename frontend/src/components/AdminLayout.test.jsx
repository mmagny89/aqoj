import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import AdminLayout from './AdminLayout'
import {
  AdminCataloguePage, AdminCommunityPage, AdminExplorerPage, AdminJobsPage, AdminMappingsPage, AdminMembersPage, AdminOverviewPage,
} from '../pages/AdminPages'

const auth = vi.hoisted(() => ({ user: { isAdmin: true } }))
vi.mock('../context/AuthContext', () => ({ useAuth: () => ({ user: auth.user }) }))
vi.mock('./AdminCatalogue', () => ({
  JobsPanel: () => <p>Panneau des tâches</p>,
  GamesPanel: ({ filter }) => <p>Explorateur sur {filter}</p>,
}))
vi.mock('./AdminMembers', () => ({ default: () => <p>Panneau des membres</p> }))
vi.mock('../api', () => ({ getAdminStats: vi.fn() }))
import { getAdminStats } from '../api'

const stats = {
  generated_at: '2026-10-25T12:00:00+00:00',
  games: { total: 10, enriched: 4, pending: 6, stale_count: 2, no_mechanics: 1, no_image: 1, no_description: 1 },
  expansions: { total: 3, enriched: 1, pending: 2, stale_count: 0, linked: 1, unlinked: 2, no_mechanics: 0, no_image: 0 },
  users: { total: 5, with_bgg: 2, without_bgg: 3, admins: 1, collections: { total_links: 4, rated_links: 1, avg_per_user: 2, max_per_user: 3 } },
  sessions: { total: 1, this_month: 1, this_week: 1, this_year: 1, distinct_games: 1, distinct_users: 1, with_rating: 0, last_session: null },
  mappings: { total_mappings: 1, families_used: 1, unmapped_bgg_mechanics: 3 },
  themes: { total_mappings: 1, distinct_labels: 1, distinct_groups: 1, unmapped_bgg_categories: 0 },
}

const renderAt = (url) => render(
  <MemoryRouter initialEntries={[url]}>
    <Routes>
      <Route path="/" element={<p>Accueil</p>} />
      <Route path="/admin" element={<AdminLayout />}>
        <Route index element={<AdminOverviewPage />} />
        <Route path="catalogue" element={<AdminCataloguePage />} />
        <Route path="explorer" element={<AdminExplorerPage />} />
        <Route path="taches" element={<AdminJobsPage />} />
        <Route path="membres" element={<AdminMembersPage />} />
        <Route path="communaute" element={<AdminCommunityPage />} />
        <Route path="mappings" element={<AdminMappingsPage />} />
      </Route>
    </Routes>
  </MemoryRouter>,
)

describe('Administration', () => {
  beforeEach(() => {
    auth.user = { isAdmin: true }
    getAdminStats.mockResolvedValue(stats)
  })

  it('renvoie à l’accueil qui n’est pas administrateur', () => {
    auth.user = { isAdmin: false }
    renderAt('/admin')

    expect(screen.getByText('Accueil')).toBeInTheDocument()
    expect(getAdminStats).not.toHaveBeenCalled()
  })

  it('ouvre la vue d’ensemble et ne liste que ce qui demande une action', async () => {
    renderAt('/admin')

    expect(await screen.findByRole('heading', { level: 1, name: 'Vue d’ensemble' })).toBeInTheDocument()
    const aTraiter = screen.getByRole('region', { name: 'À traiter' })
    expect(aTraiter).toHaveTextContent('6 jeux')
    expect(aTraiter).toHaveTextContent('2 extensions')
    expect(aTraiter).toHaveTextContent('3 mécaniques BGG')
    expect(aTraiter).not.toHaveTextContent('catégories BGG')
    expect(screen.getByRole('link', { name: 'Mapper' })).toHaveAttribute('href', '/admin/mappings/mecaniques?tab=unmapped')
  })

  it('marque l’entrée courante du sous-menu et navigue entre les pages', async () => {
    renderAt('/admin')
    await screen.findByRole('heading', { level: 1, name: 'Vue d’ensemble' })

    const menu = screen.getByRole('navigation', { name: 'Administration' })
    expect(menu.querySelector('[aria-current="page"]')).toHaveTextContent('Vue d’ensemble')

    await userEvent.click(screen.getByRole('link', { name: 'Tâches' }))

    expect(await screen.findByText('Panneau des tâches')).toBeInTheDocument()
    expect(menu.querySelector('[aria-current="page"]')).toHaveTextContent('Tâches')
  })

  it('garde le filtre de l’explorateur dans l’adresse', async () => {
    renderAt('/admin/explorer?filtre=stale')
    expect(await screen.findByText('Explorateur sur stale')).toBeInTheDocument()
  })

  it('une carte du catalogue mène à l’explorateur sur son filtre', async () => {
    renderAt('/admin/catalogue')

    await userEvent.click(await screen.findByRole('link', { name: /Fiches CSV en attente/ }))

    expect(screen.getByText('Explorateur sur pending')).toBeInTheDocument()
  })

  it('reprend les anciennes adresses à onglets', async () => {
    renderAt('/admin?onglet=membres')
    expect(await screen.findByText('Panneau des membres')).toBeInTheDocument()
  })

  it('montre la communauté et les mappings', async () => {
    renderAt('/admin/communaute')
    expect(await screen.findByRole('region', { name: 'Parties enregistrées' })).toBeInTheDocument()

    // Le menu signale ce qui reste à mapper (3 mécaniques dans ce jeu d'essai).
    expect(screen.getByRole('link', { name: /^Mappings\s*3 à traiter/ })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('link', { name: /^Mappings/ }))
    expect(await screen.findByRole('link', { name: 'Gérer les mécaniques' })).toBeInTheDocument()
  })
})
