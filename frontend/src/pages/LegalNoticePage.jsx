import LegalPage, { LegalSection } from '../components/LegalPage'
import { publisher, host } from '../legal'

export default function LegalNoticePage() {
  return (
    <LegalPage title="Mentions légales" updated="novembre 2026">
      <LegalSection title="Éditeur du site">
        <p>
          « À quoi on joue » est édité par l'{publisher.form} {publisher.name}, à titre non commercial.
        </p>
        <p>Siège : {publisher.address}</p>
        <p>Identification (RNA ou SIREN) : {publisher.id}</p>
        <p>Directeur ou directrice de la publication : {publisher.director}</p>
        <p>Contact : {publisher.email}</p>
      </LegalSection>

      <LegalSection title="Hébergement">
        <p>
          {host.name}, hébergement en Europe. Adresse de l'hébergeur : {host.address}
        </p>
      </LegalSection>

      <LegalSection title="Données de jeux">
        <p>
          Les fiches de jeux, les notes et les images proviennent de{' '}
          <a href="https://boardgamegeek.com" className="text-amber-700 underline" target="_blank" rel="noopener noreferrer">
            BoardGameGeek
          </a>{' '}
          et restent la propriété de leurs auteurs et de BoardGameGeek. Ce site n'est ni affilié à BoardGameGeek ni
          soutenu par lui.
        </p>
      </LegalSection>

      <LegalSection title="Propriété intellectuelle">
        <p>
          Le code et les contenus propres au site sont protégés. Toute reproduction sans autorisation écrite de
          l'éditeur est interdite.
        </p>
      </LegalSection>

      <LegalSection title="Données personnelles">
        <p>La politique de confidentialité décrit les données collectées et vos droits.</p>
      </LegalSection>
    </LegalPage>
  )
}
