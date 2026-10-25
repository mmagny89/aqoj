import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { GamesPanel, JobsPanel } from './AdminCatalogue'

vi.mock('../api', () => ({
  getAdminJobs: vi.fn(),
  getAdminGameFilters: vi.fn(),
  getAdminGames: vi.fn(),
  getAdminGame: vi.fn(),
}))
import { getAdminGameFilters, getAdminGames, getAdminJobs } from '../api'

const run = { id: 1, command: 'app:bgg:sync-new', arguments: '', status: 'failed', startedAt: '2026-10-25T04:00:00+01:00', durationSeconds: 75, metrics: { importes: 4 }, error: 'BGG indisponible' }

describe('AdminCatalogue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getAdminJobs.mockResolvedValue({ runs: [run], last: [run] })
    getAdminGameFilters.mockResolvedValue(Object.fromEntries(['all', 'pending', 'never_attempted', 'retry_later', 'unanswered', 'enriched', 'stale', 'no_image', 'no_description', 'no_mechanics', 'unlinked_expansions'].map((k, i) => [k, i * 1000])))
    getAdminGames.mockResolvedValue({ total: 1, page: 1, perPage: 50, items: [{ bggId: '13', name: 'Catan', source: 'bgg_csv', hasImage: false, hasDescription: false, lastSyncedAt: null, lastFetchAttemptAt: null }] })
  })

  it('montre le bilan et l’erreur des tâches', async () => {
    render(<JobsPanel />)

    expect(await screen.findAllByText('Nouveautés BGG')).not.toHaveLength(0)
    expect(screen.getAllByRole('alert')[0]).toHaveTextContent('BGG indisponible')
    expect(screen.getAllByText('Échec').length).toBeGreaterThan(0)
  })

  it('filtre les jeux par état et relance la requête', async () => {
    const onFilter = vi.fn()
    render(<GamesPanel filter="pending" onFilter={onFilter} />)

    expect(await screen.findByText('Catan')).toBeInTheDocument()
    expect(getAdminGames).toHaveBeenLastCalledWith({ filter: 'pending', q: '', page: 1 })
    await userEvent.click(screen.getByRole('button', { name: /Sans image/ }))

    expect(onFilter).toHaveBeenCalledWith('no_image')
  })
})
