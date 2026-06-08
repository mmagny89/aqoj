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
  ;(params.engines ?? []).forEach(e => qs.append('engines[]', e))
  return request('/games/search' + (qs.toString() ? '?' + qs : ''))
}

export const getGame = (id) => request(`/games/${id}`)

export const getRecommendations = (params = {}) => {
  const { players, maxTime, families = [], categories = [], scope = 'collection' } = params
  const qs = new URLSearchParams()
  if (players) qs.set('players', players)
  if (maxTime) qs.set('maxTime', maxTime)
  if (scope !== 'collection') qs.set('scope', scope)
  families.forEach(f => qs.append('families[]', f))
  categories.forEach(c => qs.append('categories[]', c))
  return request('/games/recommendation?' + qs)
}

export const getForgottenGems = () => request('/games/forgotten')

export const getHomeRecommendations = () => request('/games/home-reco')
export const getSimilarGames = (id, limit = 5) => request(`/games/${id}/similar?limit=${limit}`)

// Préférences utilisateur
export const getUserPreferences = () => request('/user/preferences')
export const recomputeUserPreferences = () => request('/user/preferences/recompute', { method: 'POST' })

// Library (collection personnelle)
// played : true = joués (notés BGG), false = pas encore joués, undefined = tous
export const getLibrary = (page = 1, played = undefined, isExpansion = undefined) => {
  const qs = new URLSearchParams()
  if (page > 1) qs.set('page', page)
  if (played !== undefined) qs.set('played', played ? '1' : '0')
  if (isExpansion !== undefined) qs.set('isExpansion', isExpansion ? '1' : '0')
  const q = qs.toString()
  return request('/library' + (q ? '?' + q : ''))
}

// BGG import
export const importBgg = (username) =>
  request('/bgg/import', { method: 'POST', body: JSON.stringify({ username }) })

// Admin — Mechanic Mappings
export const getMechanicMappings = () =>
  request('/admin/mechanic-mappings')

export const createMechanicMapping = (data) =>
  request('/admin/mechanic-mappings', { method: 'POST', body: JSON.stringify(data) })

export const updateMechanicMapping = (id, data) =>
  request(`/admin/mechanic-mappings/${id}`, { method: 'PUT', body: JSON.stringify(data) })

export const deleteMechanicMapping = (id) =>
  request(`/admin/mechanic-mappings/${id}`, { method: 'DELETE' }).catch(() => {})

export const recomputeMechanicFamilies = () =>
  request('/admin/mechanic-mappings/recompute', { method: 'POST' })

export const getUnmappedMechanics = () =>
  request('/admin/mechanic-mappings/unmapped')

export const getAdminStats = () =>
  request('/admin/stats')

// Theme mappings
export const getThemeMappings = () => request('/theme-mappings')
export const getUnmappedBggCategories = () => request('/admin/theme-mappings/unmapped')
export const createThemeMapping = (data) =>
  request('/admin/theme-mappings', { method: 'POST', body: JSON.stringify(data) })
export const updateThemeMapping = (id, data) =>
  request(`/admin/theme-mappings/${id}`, { method: 'PUT', body: JSON.stringify(data) })
export const deleteThemeMapping = (id) =>
  request(`/admin/theme-mappings/${id}`, { method: 'DELETE' }).catch(() => {})

// Sessions
export const getSessions = () => request('/sessions')

export const createSession = (data) =>
  request('/sessions', { method: 'POST', body: JSON.stringify(data) })
