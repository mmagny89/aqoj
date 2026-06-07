import { Routes, Route } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import Navigation from './components/Navigation'
import ProtectedRoute from './components/ProtectedRoute'
import HomePage from './pages/HomePage'
import RecommendationPage from './pages/RecommendationPage'
import ImportBggPage from './pages/ImportBggPage'
import LibraryPage from './pages/LibraryPage'
import SessionsPage from './pages/SessionsPage'
import SearchPage from './pages/SearchPage'
import GameDetailPage from './pages/GameDetailPage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import AdminMechanicsPage from './pages/AdminMechanicsPage'
import AdminDashboardPage from './pages/AdminDashboardPage'
import AdminThemesPage from './pages/AdminThemesPage'

export default function App() {
  return (
    <AuthProvider>
      <div className="min-h-screen bg-stone-50 text-stone-900">
        <Navigation />
        <main className="pt-16">
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/recommander" element={<RecommendationPage />} />
            <Route path="/rechercher" element={<SearchPage />} />
            <Route path="/games/:id" element={<GameDetailPage />} />
            <Route path="/ludotheque" element={<LibraryPage />} />
            <Route path="/connexion" element={<LoginPage />} />
            <Route path="/inscription" element={<RegisterPage />} />
            <Route path="/importer" element={
              <ProtectedRoute><ImportBggPage /></ProtectedRoute>
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
        </main>
      </div>
    </AuthProvider>
  )
}
