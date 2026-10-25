import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const root = resolve('.') // Vitest tourne depuis frontend/
const html = readFileSync(resolve(root, 'index.html'), 'utf8')

describe('icônes du site', () => {
  it('déclare une favicon et une icône tactile, et leurs fichiers existent dans public/', () => {
    const hrefs = [...html.matchAll(/<link rel="(?:icon|apple-touch-icon)"[^>]*href="([^"]+)"/g)].map(m => m[1])

    expect(hrefs).toEqual(expect.arrayContaining(['/favicon.ico', '/favicon-32.png', '/apple-touch-icon.png']))
    for (const href of hrefs) expect(existsSync(resolve(root, 'public', href.slice(1))), href).toBe(true)
  })
})
