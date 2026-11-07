type InstallPromptEvent = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

let deferred: InstallPromptEvent | null = null
let installed = runningAsApp()
const listeners = new Set<() => void>()

function emit(): void {
  listeners.forEach((listener) => listener())
}

export function runningAsApp(): boolean {
  if (typeof window === 'undefined') {
    return false
  }

  const nav = window.navigator as Navigator & { standalone?: boolean }

  return (
    window.matchMedia('(display-mode: standalone)').matches || nav.standalone === true
  )
}

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferred = event as InstallPromptEvent
    emit()
  })

  window.addEventListener('appinstalled', () => {
    installed = true
    deferred = null
    emit()
  })
}

export function subscribeInstall(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function installState(): { installed: boolean; canPrompt: boolean } {
  return { installed, canPrompt: deferred !== null }
}

export async function promptInstall(): Promise<'prompted' | 'manual'> {
  if (!deferred) {
    return 'manual'
  }

  const event = deferred
  deferred = null
  emit()
  await event.prompt()
  const choice = await event.userChoice

  if (choice.outcome === 'accepted') {
    installed = true
    emit()
  }

  return 'prompted'
}
