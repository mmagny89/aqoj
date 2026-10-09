import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="mt-12 border-t border-stone-200 bg-white">
      <div className="max-w-5xl mx-auto px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-stone-500">
        <nav aria-label="Informations légales" className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
          <Link to="/mentions-legales" className="hover:text-amber-700 hover:underline">Mentions légales</Link>
          <Link to="/confidentialite" className="hover:text-amber-700 hover:underline">Confidentialité</Link>
          <Link to="/aide" className="hover:text-amber-700 hover:underline">Aide</Link>
        </nav>
        <a
          href="https://boardgamegeek.com"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-stone-300 bg-stone-50 font-semibold text-stone-700 hover:border-amber-400 hover:text-amber-700"
        >
          Powered by BGG
          <span className="sr-only"> — données de BoardGameGeek (nouvel onglet)</span>
        </a>
      </div>
    </footer>
  )
}
