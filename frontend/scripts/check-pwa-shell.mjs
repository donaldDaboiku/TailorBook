import { readFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'

const dist = resolve(import.meta.dirname, '../dist')
const swPath = resolve(dist, 'sw.js')
const manifestPath = resolve(dist, 'manifest.webmanifest')

if (!existsSync(swPath) || !existsSync(manifestPath)) {
  console.error('PWA build artifacts missing. Run npm run build first.')
  process.exit(1)
}

const sw = readFileSync(swPath, 'utf8')
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))

const checks = [
  [manifest.name === 'TailorMate', 'manifest name'],
  [manifest.display === 'standalone', 'manifest display'],
  [Array.isArray(manifest.icons) && manifest.icons.length >= 2, 'manifest icons'],
  [sw.includes('/api'), 'service worker mentions /api'],
  [sw.includes('NetworkOnly'), 'service worker uses NetworkOnly for API'],
  [!/precache.*\/api\//i.test(sw), 'service worker does not precache /api'],
]

const failed = checks.filter(([ok]) => !ok).map(([, label]) => label)

if (failed.length > 0) {
  console.error('PWA shell check failed:', failed.join(', '))
  process.exit(1)
}

console.log('PWA shell check passed')
