export default function LegalPage({ title, updated, children }) {
  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">
      <header>
        <h1 className="text-4xl leading-tight sm:text-5xl">{title}</h1>
        {updated && <p className="mt-1 text-stone-600">Dernière mise à jour : {updated}</p>}
      </header>
      <div className="panel flex flex-col gap-6 p-5 leading-relaxed text-stone-800 sm:p-8">
        {children}
      </div>
    </div>
  )
}

export function LegalSection({ title, children }) {
  return (
    <section className="flex flex-col gap-2">
      <h2 className="text-2xl">{title}</h2>
      {children}
    </section>
  )
}
