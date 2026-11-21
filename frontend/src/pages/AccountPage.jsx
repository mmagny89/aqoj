import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { changePassword, deleteAccount, exportAccount, getUserPreferences, updateMemberNumber } from '../api'
import TasteForm, { TASTES_MAX } from '../components/TasteForm'
import { ErrorMessage, Field, PasswordField } from '../components/forms'
import VoteHistory from '../components/VoteHistory'

const TABS = [
  { id: 'votes', label: 'Mes votes' },
  { id: 'gouts', label: 'Mes goûts' },
  { id: 'profil', label: 'Profil et sécurité' },
  { id: 'donnees', label: 'Mes données' },
]
const tabFromHash = (hash) => TABS.find(t => `#${t.id}` === hash)?.id ?? 'votes'

export default function AccountPage() {
  const { user, login, logout } = useAuth()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const [memberNumber, setMemberNumber] = useState(user?.memberNumber ?? '')
  const [memberMessage, setMemberMessage] = useState(null)
  const [memberError, setMemberError] = useState(null)

  const [passwords, setPasswords] = useState({ current: '', next: '', confirm: '' })
  const [passwordMessage, setPasswordMessage] = useState(null)
  const [passwordError, setPasswordError] = useState(null)
  const [changing, setChanging] = useState(false)

  // Goûts déclarés : le formulaire n'apparaît qu'une fois ceux du compte connus, pour ne pas partir de zéro.
  const [declared, setDeclared] = useState(null)
  const { hash } = useLocation()
  // Le choix du joueur l'emporte ; sans choix, l'ancre de l'adresse (`/compte#gouts`) désigne l'entrée.
  const [picked, setTab] = useState(null)
  const tab = picked ?? tabFromHash(hash)

  useEffect(() => {
    getUserPreferences()
      .then(prefs => setDeclared(prefs?.declaredFamilies ?? []))
      .catch(() => setDeclared([]))
  }, [])

  const token = () => localStorage.getItem('aqoj_token')

  const saveMemberNumber = async (e) => {
    e.preventDefault()
    setMemberError(null)
    setMemberMessage(null)
    try {
      const updated = await updateMemberNumber(memberNumber)
      login(token(), updated)
      setMemberNumber(updated.memberNumber ?? '')
      setMemberMessage(updated.memberNumber ? `Numéro enregistré : ${updated.memberNumber}.` : 'Numéro retiré.')
    } catch (err) {
      setMemberError(err.message)
    }
  }

  const savePassword = async (e) => {
    e.preventDefault()
    setPasswordError(null)
    setPasswordMessage(null)
    if (passwords.next !== passwords.confirm) {
      setPasswordError('Les mots de passe ne correspondent pas.')
      return
    }
    setChanging(true)
    try {
      // Le jeton rendu garde cette session ouverte ; les autres appareils sont déconnectés.
      const data = await changePassword(passwords.current, passwords.next)
      login(data.token, data.user)
      setPasswords({ current: '', next: '', confirm: '' })
      setPasswordMessage('Mot de passe modifié. Les autres appareils sont déconnectés.')
    } catch (err) {
      setPasswordError(err.message)
    } finally {
      setChanging(false)
    }
  }

  const handleExport = async () => {
    setError(null)
    try {
      const data = await exportAccount()
      const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
      const link = document.createElement('a')
      link.href = url
      link.download = 'aqoj-mes-donnees.json'
      link.click()
      URL.revokeObjectURL(url)
    } catch (e) {
      setError(e.message)
    }
  }

  const handleDelete = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await deleteAccount(password)
      logout()
      navigate('/')
    } catch (err) {
      setError(err.message)
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div className="flex items-center gap-4">
        <span aria-hidden="true" className="grid h-14 w-14 shrink-0 place-items-center rounded-full border-[3px] border-ink bg-brand-yellow text-2xl font-bold uppercase shadow-token-sm">
          {(user?.email ?? '?').charAt(0)}
        </span>
        <div className="min-w-0">
          <h1 className="text-4xl leading-tight sm:text-5xl">Mon compte</h1>
          <p className="mt-1 break-words text-lg text-stone-600">Connecté·e avec <strong className="text-ink">{user?.email}</strong></p>
        </div>
      </div>

      <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">
        {/* Même menu que l'administration : colonne au bureau, bande défilante sur téléphone. */}
        <nav aria-label="Mon compte" className="lg:sticky lg:top-6 lg:w-60 lg:shrink-0 lg:rounded-2xl lg:border-[3px] lg:border-ink lg:bg-snow lg:p-3 lg:shadow-token-sm">
          <div className="-mx-1 flex gap-1.5 overflow-x-auto overflow-y-hidden px-1 py-1 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:p-0">
            {TABS.map(t => (
              <button
                key={t.id}
                type="button"
                aria-current={tab === t.id ? 'page' : undefined}
                onClick={() => setTab(t.id)}
                className={'flex min-h-11 shrink-0 cursor-pointer items-center gap-3 whitespace-nowrap rounded-xl border-[3px] px-3.5 text-left font-bold transition-colors ' +
                  (tab === t.id ? 'border-ink bg-ink text-paper' : 'border-transparent hover:border-ink hover:bg-brand-yellow')}
              >
                {t.label}
              </button>
            ))}
          </div>
        </nav>

        <div className="flex min-w-0 flex-1 flex-col gap-6">
      <ErrorMessage>{error}</ErrorMessage>

        {tab === 'votes' && (
          <>
      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-votes">
        <h2 id="titre-votes" className="text-2xl">Mes votes sur les jeux</h2>
        <p className="text-stone-600">
          Tous vos retours sur les propositions. Changez un vote ou annulez-le : le jeu pourra de nouveau vous être proposé.
        </p>
        <VoteHistory />
      </section>
          </>
        )}
        {tab === 'gouts' && (
          <>
      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-gouts-compte">
        <h2 id="titre-gouts-compte" className="text-2xl">Mes goûts</h2>
        <p className="text-stone-600">
          Les styles de jeu que vous aimez, jusqu'à {TASTES_MAX}. Ils orientent les suggestions de l'accueil, même sans compte BGG ni
          historique de parties ; décochez tout pour les effacer.
        </p>
        {declared === null
          ? <p role="status" className="text-stone-600">Chargement…</p>
          : <TasteForm initial={declared} allowEmpty labelledBy="titre-gouts-compte" />}
      </section>
          </>
        )}
        {tab === 'profil' && (
          <>
      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-adherent">
        <h2 id="titre-adherent" className="text-2xl">Mon numéro d'adhérent</h2>
        <p className="text-stone-600">
          Votre numéro d'adhérent à l'association Envie de Jouer, s'il vous en a été remis (par exemple EDJ-0123).
          Il aide l'association à vous reconnaître ; il n'ouvre aucun droit à lui seul.
        </p>
        <form onSubmit={saveMemberNumber} className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <Field className="sm:w-72" label="Numéro d'adhérent" value={memberNumber} onChange={(e) => setMemberNumber(e.target.value)} placeholder="EDJ-0123" autoComplete="off" />
          <button type="submit" className="btn btn-secondary">Enregistrer</button>
        </form>
        <ErrorMessage>{memberError}</ErrorMessage>
        {memberMessage && <p role="status" className="font-bold text-brand-green">{memberMessage}</p>}
      </section>

      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-mdp">
        <h2 id="titre-mdp" className="text-2xl">Changer mon mot de passe</h2>
        <form onSubmit={savePassword} className="flex flex-col gap-3">
          <PasswordField label="Mot de passe actuel" autoComplete="current-password" required value={passwords.current} onChange={(e) => setPasswords(p => ({ ...p, current: e.target.value }))} />
          <PasswordField label="Nouveau mot de passe" hint="(8 caractères minimum)" autoComplete="new-password" required minLength={8} value={passwords.next} onChange={(e) => setPasswords(p => ({ ...p, next: e.target.value }))} />
          <PasswordField label="Confirmer le nouveau mot de passe" autoComplete="new-password" required value={passwords.confirm} onChange={(e) => setPasswords(p => ({ ...p, confirm: e.target.value }))} />
          <ErrorMessage>{passwordError}</ErrorMessage>
          {passwordMessage && <p role="status" className="font-bold text-brand-green">{passwordMessage}</p>}
          <div>
            <button type="submit" disabled={changing} className="btn btn-secondary">
              {changing ? 'Enregistrement…' : 'Changer mon mot de passe'}
            </button>
          </div>
        </form>
      </section>
          </>
        )}
        {tab === 'donnees' && (
          <>
      <section className="panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="titre-export">
        <h2 id="titre-export" className="text-2xl">Récupérer mes données</h2>
        <p className="text-stone-600">
          Un fichier JSON avec votre compte, votre ludothèque, vos notes, vos parties et vos préférences.
        </p>
        <div>
          <button type="button" onClick={handleExport} className="btn btn-secondary">
            Télécharger mes données
          </button>
        </div>
      </section>

      <section className="panel flex flex-col gap-3 border-brand-red p-5 sm:p-6" aria-labelledby="titre-suppression">
        <h2 id="titre-suppression" className="text-2xl">Supprimer mon compte</h2>
        <p className="text-stone-600">
          Votre compte, votre ludothèque, vos parties et vos préférences sont effacés immédiatement et
          définitivement.
        </p>
        {confirming ? (
          <form onSubmit={handleDelete} className="flex flex-col gap-3">
            <PasswordField
              label="Votre mot de passe, pour confirmer"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <div className="flex flex-wrap gap-3">
              <button type="submit" disabled={busy} className="btn bg-brand-red">
                Supprimer définitivement
              </button>
              <button type="button" onClick={() => { setConfirming(false); setPassword('') }} className="btn bg-paper">
                Annuler
              </button>
            </div>
          </form>
        ) : (
          <div>
            <button type="button" onClick={() => setConfirming(true)} className="btn bg-paper">
              Supprimer mon compte
            </button>
          </div>
        )}
      </section>
          </>
        )}
        </div>
      </div>
    </div>
  )
}
