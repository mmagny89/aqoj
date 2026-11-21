import { useCallback, useEffect, useMemo, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { filtersFromParams, filtersToParams } from '../utils/contexte'

/**
 * Filtres de contexte portés par l'adresse : `[filtres, setFiltres]`, `setFiltres` accepte un objet ou une fonction.
 * `setFiltres` garde la même identité d'un rendu à l'autre (celle de `setSearchParams` change à chaque navigation) :
 * un effet qui l'appelle ne se relance pas à cause de lui.
 */
export function useContextFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const key = JSON.stringify(filtersFromParams(searchParams))
  // `key` est la valeur sérialisée des filtres : l'objet ne change qu'avec elle.
  const filters = useMemo(() => JSON.parse(key), [key])

  const latest = useRef(setSearchParams)
  useEffect(() => { latest.current = setSearchParams })

  const setFilters = useCallback((next) => {
    latest.current(prev => {
      const current = filtersFromParams(prev)
      return filtersToParams(typeof next === 'function' ? next(current) : next, prev)
    }, { replace: true })
  }, [])

  return [filters, setFilters]
}
