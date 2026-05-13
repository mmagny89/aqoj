const BASE = '/api'

async function request(path, options = {}) {
  const res = await fetch(BASE + path, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: 'Erreur réseau' }))
    throw new Error(err.error || `HTTP ${res.status}`)
  }
  return res.json()
}

export const getGames = (params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v != null && v !== ''))
  return request('/games' + (qs.toString() ? '?' + qs : ''))
}

export const searchGames = (params = {}) => {
  const qs = new URLSearchParams()
  if (params.q) qs.set('q', params.q)
  if (params.players) qs.set('players', params.players)
  if (params.maxTime) qs.set('maxTime', params.maxTime)
  if (params.category) qs.set('category', params.category)
  return request('/games/search' + (qs.toString() ? '?' + qs : ''))
}

export const getGame = (id) => request(`/games/${id}`)

export const getRecommendations = (params = {}) => {
  const { players, maxTime, categories = [] } = params
  const qs = new URLSearchParams()
  if (players) qs.set('players', players)
  if (maxTime) qs.set('maxTime', maxTime)
  categories.forEach(c => qs.append('categories[]', c))
  return request('/games/recommendation?' + qs)
}

export const getForgottenGems = () => request('/games/forgotten')

export const importBgg = (username) =>
  request('/bgg/import', { method: 'POST', body: JSON.stringify({ username }) })

export const getSessions = () => request('/sessions')

export const createSession = (data) =>
  request('/sessions', { method: 'POST', body: JSON.stringify(data) })
