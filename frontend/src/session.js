/**
 * Durée de vie de la session côté navigateur. Le jeton dure 8 h ; on le renouvelle dès qu'il lui reste moins de
 * la moitié (4 h), tant que la personne est là. Le serveur plafonne la session à 30 jours depuis la connexion :
 * au-delà, le renouvellement répond 401 et la personne se reconnecte.
 */
export const RENEW_MARGIN_SECONDS = 4 * 60 * 60

/** Date d'expiration (secondes Unix) lue dans le jeton, sans vérifier sa signature : le serveur seul fait foi. */
export function tokenExpiry(token) {
  try {
    const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    const { exp } = JSON.parse(atob(payload))
    return Number.isFinite(exp) ? exp : null
  } catch {
    return null
  }
}

/** Faut-il renouveler ? Oui s'il reste moins de la marge ; non s'il est illisible ou déjà expiré (le serveur dira 401). */
export function needsRenewal(token, nowSeconds = Math.floor(Date.now() / 1000), margin = RENEW_MARGIN_SECONDS) {
  const exp = tokenExpiry(token)
  if (exp === null || exp <= nowSeconds) return false
  return exp - nowSeconds < margin
}
