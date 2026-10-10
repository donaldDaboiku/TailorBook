const keyFor = (userId: number) => `tailormate.tutorial.seen.${userId}`

export function hasSeenTutorial(userId: number): boolean {
  return localStorage.getItem(keyFor(userId)) === '1'
}

export function markTutorialSeen(userId: number): void {
  localStorage.setItem(keyFor(userId), '1')
}

export function markTutorialUnseen(userId: number): void {
  localStorage.removeItem(keyFor(userId))
}
