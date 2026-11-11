import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { GamesPanel, JobsPanel } from './AdminCatalogue'

vi.mock('../api', () => ({
  getAdminJobs: vi.fn(),
  getAdminGameFilters: vi.fn(),
  getAdminGames: vi.fn(),
  getAdminGame: vi.fn(),
  stopAdminJob: vi.fn(),
  killAdminJob: vi.fn(),
}))
import { getAdminGameFilters, getAdminGames, getAdminJobs, killAdminJob, stopAdminJob } from '../api'

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

  describe('arrêt d’une tâche', () => {
    const enCours = { id: 8, command: 'app:bgg:enrich-pending', arguments: '--mode=pending', status: 'running', startedAt: '2026-10-25T15:05:00+01:00', durationSeconds: null, metrics: {}, error: null, stopRequestedAt: null }
    const show = (r) => getAdminJobs.mockResolvedValue({ runs: [r], last: [r] })

    it('demande l’arrêt d’une tâche en cours puis recharge la liste', async () => {
      show(enCours)
      stopAdminJob.mockResolvedValue({ id: 8, stopRequested: true })
      render(<JobsPanel />)

      await userEvent.click((await screen.findAllByRole('button', { name: 'Arrêter : Enrichissement BGG' }))[0])

      expect(stopAdminJob).toHaveBeenCalledWith(8)
      expect(getAdminJobs).toHaveBeenCalledTimes(2)
      expect(killAdminJob).not.toHaveBeenCalled()
    })

    it('n’offre pas l’arrêt forcé avant la demande, puis l’offre avec confirmation', async () => {
      show(enCours)
      render(<JobsPanel />)
      await screen.findAllByRole('button', { name: 'Arrêter : Enrichissement BGG' })
      expect(screen.queryByRole('button', { name: /Forcer l’arrêt/ })).not.toBeInTheDocument()
    })

    it('après la demande, propose de forcer l’arrêt, et ne le fait qu’après confirmation', async () => {
      show({ ...enCours, stopRequestedAt: '2026-10-25T15:10:00+01:00' })
      killAdminJob.mockResolvedValue({ id: 8, terminated: 1 })
      render(<JobsPanel />)

      expect((await screen.findAllByRole('status'))[0]).toHaveTextContent('Arrêt demandé')
      expect(screen.queryByRole('button', { name: /^Arrêter/ })).not.toBeInTheDocument()

      await userEvent.click(screen.getAllByRole('button', { name: 'Forcer l’arrêt : Enrichissement BGG' })[0])
      expect(killAdminJob).not.toHaveBeenCalled()
      expect(screen.getAllByRole('group', { name: 'Confirmer l’arrêt forcé' })[0]).toHaveTextContent('lot en cours sera perdu')

      await userEvent.click(screen.getAllByRole('button', { name: 'Confirmer l’arrêt forcé' })[0])
      expect(killAdminJob).toHaveBeenCalledWith(8)
    })

    it('permet d’annuler la confirmation sans rien forcer', async () => {
      show({ ...enCours, stopRequestedAt: '2026-10-25T15:10:00+01:00' })
      render(<JobsPanel />)

      await userEvent.click((await screen.findAllByRole('button', { name: /Forcer l’arrêt/ }))[0])
      await userEvent.click(screen.getAllByRole('button', { name: 'Annuler' })[0])

      expect(killAdminJob).not.toHaveBeenCalled()
      expect(screen.queryByRole('group', { name: 'Confirmer l’arrêt forcé' })).not.toBeInTheDocument()
    })

    it('propose de clôturer une exécution interrompue, sans bouton d’arrêt doux', async () => {
      show({ ...enCours, status: 'interrupted' })
      render(<JobsPanel />)

      expect((await screen.findAllByRole('button', { name: /Forcer l’arrêt/ })).length).toBeGreaterThan(0)
      expect(screen.queryByRole('button', { name: /^Arrêter/ })).not.toBeInTheDocument()
    })

    it('affiche l’échec de la demande', async () => {
      show(enCours)
      stopAdminJob.mockRejectedValue(new Error('Cette exécution n’est plus en cours.'))
      render(<JobsPanel />)

      await userEvent.click((await screen.findAllByRole('button', { name: /^Arrêter/ }))[0])

      expect((await screen.findAllByRole('alert'))[0]).toHaveTextContent('n’est plus en cours')
    })

    it('ne propose rien pour une exécution terminée, et nomme l’état « Arrêtée »', async () => {
      show({ ...enCours, status: 'cancelled', durationSeconds: 90 })
      render(<JobsPanel />)

      expect((await screen.findAllByText('Arrêtée')).length).toBeGreaterThan(0)
      expect(screen.queryByRole('button', { name: /Arrêter|Forcer/ })).not.toBeInTheDocument()
    })
  })
})
