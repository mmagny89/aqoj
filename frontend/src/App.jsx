import { Routes, Route } from 'react-router-dom'
import Navigation from './components/Navigation'
import HomePage from './pages/HomePage'
import RecommendationPage from './pages/RecommendationPage'
import ImportBggPage from './pages/ImportBggPage'
import LibraryPage from './pages/LibraryPage'
import SessionsPage from './pages/SessionsPage'
import SearchPage from './pages/SearchPage'
import GameDetailPage from './pages/GameDetailPage'

export default function App() {
  return (
    <div className="min-h-screen bg-stone-50 text-stone-900">
      <Navigation />
      <main className="pt-16">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/recommander" element={<RecommendationPage />} />
          <Route path="/rechercher" element={<SearchPage />} />
          <Route path="/games/:id" element={<GameDetailPage />} />
          <Route path="/importer" element={<ImportBggPage />} />
          <Route path="/ludotheque" element={<LibraryPage />} />
          <Route path="/parties" element={<SessionsPage />} />
        </Routes>
      </main>
    </div>
  )
}
