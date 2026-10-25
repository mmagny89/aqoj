import { useId, useState } from 'react'
import { EyeIcon, EyeOffIcon } from './icons'

/* Champ de formulaire du thème : libellé relié à son champ, aide facultative, cible de 48 px. */
export const inputClass =
  'min-h-12 w-full rounded-xl border-[3px] border-ink bg-paper px-4 text-base placeholder:text-stone-400'

export function Field({ label, hint, className = '', ...props }) {
  const id = useId()
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block font-bold">
        {label}
        {hint && <span className="ml-1.5 font-normal text-stone-600">{hint}</span>}
      </label>
      <input id={id} className={inputClass} {...props} />
    </div>
  )
}

/* Champ de mot de passe avec l'œil : « Afficher » est un bouton à bascule, jamais une case cachée. */
export function PasswordField({ label, hint, className = '', ...props }) {
  const id = useId()
  const [visible, setVisible] = useState(false)
  const Icon = visible ? EyeOffIcon : EyeIcon
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block font-bold">
        {label}
        {hint && <span className="ml-1.5 font-normal text-stone-600">{hint}</span>}
      </label>
      <div className="relative">
        <input id={id} type={visible ? 'text' : 'password'} className={`${inputClass} pr-14`} {...props} />
        <button
          type="button"
          onClick={() => setVisible(v => !v)}
          aria-pressed={visible}
          aria-label={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          className="absolute right-1 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-stone-700 hover:bg-amber-200"
        >
          <Icon width={22} height={22} />
        </button>
      </div>
    </div>
  )
}

export function SelectField({ label, className = '', children, ...props }) {
  const id = useId()
  return (
    <div className={className}>
      <label htmlFor={id} className="mb-1 block font-bold">{label}</label>
      <select id={id} className={inputClass} {...props}>{children}</select>
    </div>
  )
}

export function ErrorMessage({ children }) {
  if (!children) return null
  return (
    <p role="alert" className="rounded-xl border-[3px] border-brand-red bg-amber-100 p-3 font-bold">
      {children}
    </p>
  )
}

/* Carte centrée avec la mascotte, pour connexion et inscription. */
export function AuthCard({ title, children, footer }) {
  return (
    <div className="mx-auto flex max-w-md flex-col gap-5 px-4 py-8 sm:py-12">
      <section className="panel flex flex-col gap-5 p-6 shadow-token-lg sm:p-8">
        <div className="flex items-center gap-4">
          <img src="/edj-mascotte.png" alt="" width="80" height="89" className="h-20 w-auto shrink-0" />
          <h1 className="text-4xl leading-tight">{title}</h1>
        </div>
        {children}
      </section>
      {footer && <p className="text-center text-lg">{footer}</p>}
    </div>
  )
}
