// Identité de l'éditeur et de l'hébergeur, affichée par les pages légales.
// Les valeurs viennent de l'environnement de build (VITE_PUBLISHER_NAME, VITE_PUBLISHER_FORM, VITE_PUBLISHER_ADDRESS,
// VITE_PUBLISHER_ID, VITE_PUBLICATION_DIRECTOR, VITE_CONTACT_EMAIL, VITE_HOST_NAME, VITE_HOST_ADDRESS) ; tant qu'elles
// manquent, les pages l'affichent en clair plutôt que d'inventer.
export const TO_FILL = '[à renseigner avant la mise en ligne]'

const env = import.meta.env

export const publisher = {
  name: env.VITE_PUBLISHER_NAME || 'Envie de Jouer',
  form: env.VITE_PUBLISHER_FORM || 'association régie par la loi du 1er juillet 1901',
  address: env.VITE_PUBLISHER_ADDRESS || 'Maison des Associations, 89300 Joigny',
  // Numéro RNA ou SIREN de l'association.
  id: env.VITE_PUBLISHER_ID || 'RNA W893006812 — SIRET 920 202 488 00028',
  director: env.VITE_PUBLICATION_DIRECTOR || 'Céline Grégoire, présidente de l\'association',
  email: env.VITE_CONTACT_EMAIL || 'enviedejouer89300@gmail.com',
}

export const host = {
  // Entité contractante d'Hostinger pour l'UE, d'après ses conditions de service (identique à celle des mentions légales de enviedejouer89.fr).
  name: env.VITE_HOST_NAME || 'Hostinger International Ltd.',
  address: env.VITE_HOST_ADDRESS || '61 Lordou Vironos Street, 6023 Larnaca, Chypre',
}

// Sous-traitant d'envoi des e-mails transactionnels.
export const mailer = { name: 'Brevo', url: 'https://www.brevo.com' }
