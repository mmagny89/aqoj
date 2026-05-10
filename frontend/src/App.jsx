import { useState, useEffect } from 'react'

function App() {
  const [apiStatus, setApiStatus] = useState(null)

  useEffect(() => {
    fetch('/api/health')
      .then((res) => {
        if (!res.ok) throw new Error('API non disponible')
        return res.json()
      })
      .then((data) => setApiStatus(data.status))
      .catch(() => setApiStatus('error'))
  }, [])

  return (
    <div className="min-h-screen bg-gray-950 flex flex-col items-center justify-center">
      <h1 className="text-6xl font-bold text-white tracking-tight select-none">
        A quoi on joue
      </h1>

      {apiStatus && (
        <p
          className={`mt-6 text-sm font-mono ${
            apiStatus === 'ok' ? 'text-emerald-400' : 'text-red-400'
          }`}
        >
          api: {apiStatus}
        </p>
      )}
    </div>
  )
}

export default App
