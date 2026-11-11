import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, screen } from '@testing-library/react'
import { AuthProvider, useAuth } from './AuthContext'

vi.mock('../api', () => ({ getMe: vi.fn(), refreshSession: vi.fn() }))
import { getMe, refreshSession } from '../api'

const jeton = (secondsLeft) => {
  const exp = Math.floor(Date.now() / 1000) + secondsLeft
  return `x.${btoa(JSON.stringify({ exp })).replace(/=+$/, '')}.y`
}

function Who() {
  const { user } = useAuth()
  return <p>{user ? `connecté : ${user.email}` : 'visiteur'}</p>
}

const mount = () => render(<AuthProvider><Who /></AuthProvider>)

describe('AuthProvider : renouvellement de la session', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    getMe.mockResolvedValue({ id: 1, email: 'a@example.test' })
  })
  afterEach(() => vi.useRealTimers())

  it('renouvelle un jeton qui expire dans moins de quatre heures et garde la session', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const ancien = jeton(3 * 3600)
    localStorage.setItem('aqoj_token', ancien)
    refreshSession.mockResolvedValue({ token: 'nouveau-jeton', user: { id: 1, email: 'a@example.test' } })

    mount()

    expect(await screen.findByText('connecté : a@example.test')).toBeInTheDocument()
    await act(async () => { await vi.advanceTimersByTimeAsync(5 * 60 * 1000) })

    await vi.waitFor(() => expect(refreshSession).toHaveBeenCalledTimes(1))
    expect(localStorage.getItem('aqoj_token')).toBe('nouveau-jeton')
  })

  it('ne renouvelle pas un jeton encore frais', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    localStorage.setItem('aqoj_token', jeton(7 * 3600))

    mount()
    await screen.findByText('connecté : a@example.test')
    await act(async () => { await vi.advanceTimersByTimeAsync(5 * 60 * 1000) })

    expect(refreshSession).not.toHaveBeenCalled()
  })

  it('réessaie au passage suivant après une panne, sans perdre la session', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    localStorage.setItem('aqoj_token', jeton(3 * 3600))
    refreshSession.mockRejectedValueOnce(new Error('Erreur réseau')).mockResolvedValue({ token: 'jeton-2', user: { id: 1, email: 'a@example.test' } })

    mount()
    await screen.findByText('connecté : a@example.test')
    await act(async () => { await vi.advanceTimersByTimeAsync(5 * 60 * 1000) })
    await vi.waitFor(() => expect(refreshSession).toHaveBeenCalledTimes(1))
    expect(localStorage.getItem('aqoj_token')).not.toBe('jeton-2')

    await act(async () => { await vi.advanceTimersByTimeAsync(5 * 60 * 1000) })

    await vi.waitFor(() => expect(localStorage.getItem('aqoj_token')).toBe('jeton-2'))
    expect(screen.getByText('connecté : a@example.test')).toBeInTheDocument()
  })
})
