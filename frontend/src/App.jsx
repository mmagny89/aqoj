import { Routes, Route } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import AppShell from './components/AppShell'
import ProtectedRoute from './components/ProtectedRoute'
import HomePage from './pages/HomePage'
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

export default function App() {
  return (
    <AuthProvider>
      <AppShell>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/recommander" element={<RecommendationPage />} />
          <Route path="/rechercher" element={<SearchPage />} />
          <Route path="/games/:id" element={<GameDetailPage />} />
          <Route path="/ludotheque" element={<LibraryPage />} />
          <Route path="/aide" element={<HelpPage />} />
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
