import { describe, expect, it } from 'vitest'
import { needsRenewal, tokenExpiry } from './session'

const jeton = (exp) => `x.${btoa(JSON.stringify({ exp })).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')}.y`
const now = 1_800_000_000

describe('session', () => {
  it('lit la date d’expiration du jeton, sans le vérifier', () => {
    expect(tokenExpiry(jeton(now + 100))).toBe(now + 100)
    expect(tokenExpiry('pas-un-jeton')).toBeNull()
  })

  it('renouvelle quand il reste moins de quatre heures', () => {
    expect(needsRenewal(jeton(now + 3 * 3600), now)).toBe(true)
    expect(needsRenewal(jeton(now + 5 * 3600), now)).toBe(false)
  })

  it('ne tente rien sur un jeton expiré ou illisible : le serveur dira 401', () => {
    expect(needsRenewal(jeton(now - 10), now)).toBe(false)
    expect(needsRenewal('illisible', now)).toBe(false)
  })
})
