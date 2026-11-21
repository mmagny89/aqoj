import { Routes, Route } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import AppShell from './components/AppShell'
import ProtectedRoute from './components/ProtectedRoute'
import HomePage from './pages/HomePage'
import LandingPage from './pages/LandingPage'
import { useAuth } from './context/AuthContext'
import RecommendationPage from './pages/RecommendationPage'
import ImportPage from './pages/ImportPage'
import LibraryPage from './pages/LibraryPage'
import SessionsPage from './pages/SessionsPage'
import SearchPage from './pages/SearchPage'
import GameDetailPage from './pages/GameDetailPage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import AdminMechanicsPage from './pages/AdminMechanicsPage'
import AdminDashboardPage from './pages/AdminDashboardPage'
import AdminThemesPage from './pages/AdminThemesPage'
import HelpPage from './pages/HelpPage'
import LegalNoticePage from './pages/LegalNoticePage'
import PrivacyPage from './pages/PrivacyPage'
import AccountPage from './pages/AccountPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import VerifyEmailPage from './pages/VerifyEmailPage'

/* Accueil : le tableau de bord pour un membre connecté, la présentation de l'association pour un visiteur. */
function Home() {
  const { user, loading } = useAuth()
  if (loading) return <ProtectedRoute><HomePage /></ProtectedRoute>
  return user ? <HomePage /> : <LandingPage />
}

function Members({ children }) {
  return <ProtectedRoute>{children}</ProtectedRoute>
}

export default function App() {
  return (
    <AuthProvider>
      <AppShell>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/recommander" element={<Members><RecommendationPage /></Members>} />
          <Route path="/rechercher" element={<Members><SearchPage /></Members>} />
          <Route path="/games/:id" element={<Members><GameDetailPage /></Members>} />
          <Route path="/ludotheque" element={<Members><LibraryPage /></Members>} />
          <Route path="/aide" element={<Members><HelpPage /></Members>} />
          <Route path="/mentions-legales" element={<LegalNoticePage />} />
          <Route path="/confidentialite" element={<PrivacyPage />} />
          <Route path="/compte" element={
            <ProtectedRoute><AccountPage /></ProtectedRoute>
          } />
          <Route path="/connexion" element={<LoginPage />} />
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/mot-de-passe-oublie" element={<ForgotPasswordPage />} />
          <Route path="/verifier-adresse" element={<VerifyEmailPage />} />
          <Route path="/reinitialiser-mot-de-passe" element={<ResetPasswordPage />} />
          <Route path="/importer" element={
            <ProtectedRoute><ImportPage /></ProtectedRoute>
          } />
          <Route path="/parties" element={
            <ProtectedRoute><SessionsPage /></ProtectedRoute>
          } />
          <Route path="/admin/mecaniques" element={
            <ProtectedRoute><AdminMechanicsPage /></ProtectedRoute>
          } />
          <Route path="/admin" element={
            <ProtectedRoute><AdminDashboardPage /></ProtectedRoute>
          } />
          <Route path="/admin/themes" element={
            <ProtectedRoute><AdminThemesPage /></ProtectedRoute>
          } />
        </Routes>
      </AppShell>
    </AuthProvider>
  )
}
