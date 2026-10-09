// Identité de l'éditeur et de l'hébergeur, affichée par les pages légales.
// Les valeurs viennent de l'environnement de build (VITE_PUBLISHER_NAME, VITE_CONTACT_EMAIL, VITE_HOST_NAME,
// VITE_HOST_ADDRESS) ; tant qu'elles manquent, les pages l'affichent en clair plutôt que d'inventer.
export const TO_FILL = '[à renseigner avant la mise en ligne]'

const env = import.meta.env

export const publisher = {
  name: env.VITE_PUBLISHER_NAME || 'Mylène Magny',
  email: env.VITE_CONTACT_EMAIL || TO_FILL,
}

export const host = {
  name: env.VITE_HOST_NAME || TO_FILL,
  address: env.VITE_HOST_ADDRESS || TO_FILL,
}
