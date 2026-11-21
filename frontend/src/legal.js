// Identité de l'éditeur et de l'hébergeur, affichée par les pages légales.
// Les valeurs viennent de l'environnement de build (VITE_PUBLISHER_NAME, VITE_PUBLISHER_FORM, VITE_PUBLISHER_ADDRESS,
// VITE_PUBLISHER_ID, VITE_PUBLICATION_DIRECTOR, VITE_CONTACT_EMAIL, VITE_HOST_NAME, VITE_HOST_ADDRESS) ; tant qu'elles
// manquent, les pages l'affichent en clair plutôt que d'inventer.
export const TO_FILL = '[à renseigner avant la mise en ligne]'

const env = import.meta.env

export const publisher = {
  name: env.VITE_PUBLISHER_NAME || 'Envie de jouer',
  form: env.VITE_PUBLISHER_FORM || 'association',
  address: env.VITE_PUBLISHER_ADDRESS || TO_FILL,
  // Numéro RNA ou SIREN de l'association.
  id: env.VITE_PUBLISHER_ID || TO_FILL,
  director: env.VITE_PUBLICATION_DIRECTOR || TO_FILL,
  email: env.VITE_CONTACT_EMAIL || TO_FILL,
}

export const host = {
  name: env.VITE_HOST_NAME || 'Hostinger',
  address: env.VITE_HOST_ADDRESS || TO_FILL,
}

// Sous-traitant d'envoi des e-mails transactionnels.
export const mailer = { name: 'Brevo', url: 'https://www.brevo.com' }
