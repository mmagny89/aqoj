import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getMe, login } from './api'

function reponse(status, corps) {
  return { status, ok: status >= 200 && status < 300, json: () => Promise.resolve(corps) }
}

describe('api', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('envoie le jeton en Bearer quand il existe', async () => {
    localStorage.setItem('aqoj_token', 'abc')
    fetch.mockResolvedValue(reponse(200, { email: 'a@example.test' }))

    await getMe()

    expect(fetch.mock.calls[0][1].headers.Authorization).toBe('Bearer abc')
  })

  it("remonte le message d'erreur de l'API", async () => {
    fetch.mockResolvedValue(reponse(401, { error: 'E-mail ou mot de passe incorrect.' }))

    await expect(login('a@example.test', 'x')).rejects.toThrow('E-mail ou mot de passe incorrect.')
  })
})
