import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="mt-12 border-t-[3px] border-ink/15 bg-paper">
      <div className="max-w-6xl mx-auto px-4 lg:px-10 py-6 pb-28 lg:pb-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-stone-600">
        <nav aria-label="Informations légales" className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
          <Link to="/mentions-legales" className="inline-flex min-h-11 items-center underline-offset-4 hover:underline">Mentions légales</Link>
          <Link to="/confidentialite" className="inline-flex min-h-11 items-center underline-offset-4 hover:underline">Confidentialité</Link>
          <Link to="/aide" className="inline-flex min-h-11 items-center underline-offset-4 hover:underline">Aide</Link>
        </nav>
        <a
          href="https://boardgamegeek.com"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex min-h-11 items-center gap-2 px-3 rounded-xl border-[3px] border-ink bg-brand-yellow font-bold text-ink"
        >
          Powered by BGG
          <span className="sr-only"> — données de BoardGameGeek (nouvel onglet)</span>
        </a>
      </div>
    </footer>
  )
}
