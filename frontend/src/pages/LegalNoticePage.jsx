import LegalPage, { LegalSection } from '../components/LegalPage'
import { publisher, host } from '../legal'

export default function LegalNoticePage() {
  return (
    <LegalPage title="Mentions légales" updated="novembre 2026">
      <LegalSection title="Éditeur du site">
        <p>
          « À quoi on joue » est édité par {publisher.name}, {publisher.form}, à titre non commercial.
        </p>
        <p>Siège : {publisher.address}</p>
        <p>Identification : {publisher.id}</p>
        <p>Directrice de la publication : {publisher.director}</p>
        <p>Contact : {publisher.email}</p>
      </LegalSection>

      <LegalSection title="Hébergement">
        <p>
          Le site est hébergé par {host.name}, {host.address} —{' '}
          <a href="https://www.hostinger.fr" className="text-amber-700 underline" target="_blank" rel="noopener noreferrer">
            hostinger.fr
          </a>
          . Les données sont hébergées en Europe.
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
