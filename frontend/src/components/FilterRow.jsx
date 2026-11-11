/* Une rangée de filtres : libellé et puces fines sur la même ligne (accueil, Explorer).
   `scroll` : sur téléphone la rangée défile au lieu de passer à la ligne, pour ne pas manger l'écran. */
export default function FilterRow({ id, label, children, scroll = false }) {
  return (
    <div role="group" aria-labelledby={id} className={`flex items-center gap-1.5 ${scroll ? '-mx-1 flex-nowrap overflow-x-auto px-1 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0' : 'flex-wrap'}`}>
      <span id={id} className="mr-1 w-16 shrink-0 text-sm font-bold">{label}</span>
      {children}
    </div>
  )
}
