const BASE = '/api'

function getToken() {
  return localStorage.getItem('aqoj_token')
}

async function request(path, options = {}) {
  const token = getToken()
  const headers = { 'Content-Type': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(BASE + path, { ...options, headers })

  if (res.status === 401 && path !== '/auth/login' && path !== '/auth/register') {
    localStorage.removeItem('aqoj_token')
    window.location.href = '/connexion'
    return
  }

  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: 'Erreur réseau' }))
    throw new Error(err.error || `HTTP ${res.status}`)
  }
  return res.json()
}

// Auth
export const register = (email, password) =>
  request('/auth/register', { method: 'POST', body: JSON.stringify({ email, password }) })

export const login = (email, password) =>
  request('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) })

export const getMe = () => request('/auth/me')

// Games
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
  if (params.page && params.page > 1) qs.set('page', params.page)
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

// Library (collection personnelle)
export const getLibrary = (page = 1) =>
  request('/library' + (page > 1 ? `?page=${page}` : ''))

// BGG import
export const importBgg = (username) =>
  request('/bgg/import', { method: 'POST', body: JSON.stringify({ username }) })

// Sessions
export const getSessions = () => request('/sessions')

export const createSession = (data) =>
  request('/sessions', { method: 'POST', body: JSON.stringify(data) })
