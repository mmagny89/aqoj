// « ce soir » suit l'heure locale : le titre de l'accueil ne ment pas à neuf heures du matin.
export function momentDeLaJournee(date = new Date()) {
  const h = date.getHours()
  if (h >= 5 && h < 12) return 'ce matin'
  if (h >= 12 && h < 14) return 'ce midi'
  if (h >= 14 && h < 18) return 'cet après-midi'
  if (h >= 18 && h < 23) return 'ce soir'
  return 'cette nuit'
}
