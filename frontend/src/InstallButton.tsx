import { useEffect, useState } from 'react'
import { installState, promptInstall, subscribeInstall } from './installPrompt'

export default function InstallButton({
  variant = 'pill',
}: {
  variant?: 'pill' | 'block'
}) {
  const [installed, setInstalled] = useState(() => installState().installed)
  const [help, setHelp] = useState(false)

  useEffect(() => subscribeInstall(() => setInstalled(installState().installed)), [])

  if (installed) {
    return variant === 'block' ? (
      <p className="status">TailorMate is already installed on this phone.</p>
    ) : null
  }

  async function onInstall() {
    const result = await promptInstall()
    setHelp(result === 'manual')
  }

  return (
    <>
      <button
        type="button"
        className={variant === 'block' ? 'install-app block' : 'install-app'}
        onClick={onInstall}
      >
        Install
      </button>
      {help && variant === 'block' && (
        <p className="status">
          This browser did not open the install box. Use the steps below.
        </p>
      )}
      {help && variant === 'pill' && (
        <div className="install-sheet" role="dialog" aria-label="Install TailorMate">
          <p>
            <strong>Android Chrome:</strong> menu (⋮) → Install app or Add to Home screen
          </p>
          <p>
            <strong>iPhone Safari:</strong> Share → Add to Home Screen
          </p>
          <button type="button" className="secondary" onClick={() => setHelp(false)}>
            Close
          </button>
        </div>
      )}
    </>
  )
}
