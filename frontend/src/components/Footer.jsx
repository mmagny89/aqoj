import { Link } from 'react-router-dom'

export default function Footer({ guest = false }) {
  return (
    <footer className="mt-8 border-t-2 border-ink/15 bg-paper">
      <div className="max-w-6xl mx-auto px-4 lg:px-10 py-2 pb-20 lg:pb-2 flex flex-col sm:flex-row items-center justify-between gap-1 sm:gap-3 text-sm text-stone-600">
        <nav aria-label="Informations légales" className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
          <Link to="/mentions-legales" className="relative inline-flex min-h-7 items-center underline-offset-4 after:absolute after:inset-x-0 after:-inset-y-2 after:content-[''] hover:underline">Mentions légales</Link>
          <Link to="/confidentialite" className="relative inline-flex min-h-7 items-center underline-offset-4 after:absolute after:inset-x-0 after:-inset-y-2 after:content-[''] hover:underline">Confidentialité</Link>
          {!guest && <Link to="/aide" className="relative inline-flex min-h-7 items-center underline-offset-4 after:absolute after:inset-x-0 after:-inset-y-2 after:content-[''] hover:underline">Aide</Link>}
        </nav>
        <a
          href="https://boardgamegeek.com"
          target="_blank"
          rel="noopener noreferrer"
          className="relative inline-flex min-h-7 items-center gap-2 rounded-lg border-2 border-ink bg-brand-yellow px-2.5 text-xs font-bold text-ink after:absolute after:inset-x-0 after:-inset-y-2 after:content-['']"
        >
          Powered by BGG
          <span className="sr-only"> — données de BoardGameGeek (nouvel onglet)</span>
        </a>
      </div>
    </footer>
  )
}
