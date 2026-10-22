export default function LegalPage({ title, updated, children }) {
  return (
    <div className="max-w-3xl mx-auto px-4 py-8 space-y-6">
      <header>
        <h1 className="text-2xl font-black text-stone-900">{title}</h1>
        {updated && <p className="text-xs text-stone-400 mt-1">Dernière mise à jour : {updated}</p>}
      </header>
      <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-5 text-sm leading-relaxed text-stone-700">
        {children}
      </div>
    </div>
  )
}

export function LegalSection({ title, children }) {
  return (
    <section className="space-y-2">
      <h2 className="text-base font-bold text-stone-900">{title}</h2>
      {children}
    </section>
  )
}
