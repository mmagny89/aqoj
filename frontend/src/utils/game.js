/** Image à afficher : la boîte de l'édition française quand BGG la connaît, sinon l'image d'origine. */
export function gameImage(game) {
  return game.editionFrImageUrl || game.imageUrl || null
}

/** « Filosofia · 2021 », ou ce qu'on en sait ; null sans édition française connue. */
export function frenchEditionLabel(game) {
  const parts = [game.editionFrPublisher, game.editionFrYear].filter(Boolean)
  return parts.length > 0 ? parts.join(' · ') : null
}

/** « Kosmos · 1995 » : éditeur et année de l'édition d'origine, ou ce qu'on en sait ; null sans rien. */
export function originalEditionLabel(game) {
  const parts = [game.publisherOriginal, game.yearPublished].filter(Boolean)
  return parts.length > 0 ? parts.join(' · ') : null
}
