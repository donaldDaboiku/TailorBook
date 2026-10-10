import type { PaymentReceipt } from './api'

const WIDTH = 720

export async function renderReceiptPng(receipt: PaymentReceipt): Promise<Blob> {
  const logo = receipt.logo_data_url
    ? await loadImage(receipt.logo_data_url)
    : null
  const height = logo ? 1180 : 1080
  const canvas = document.createElement('canvas')
  canvas.width = WIDTH
  canvas.height = height
  const ctx = canvas.getContext('2d')

  if (!ctx) {
    throw new Error('Could not draw the receipt.')
  }

  ctx.fillStyle = '#f4f1eb'
  ctx.fillRect(0, 0, WIDTH, height)

  ctx.fillStyle = '#1f3d34'
  ctx.fillRect(0, 0, WIDTH, logo ? 250 : 180)

  let y = 36
  if (logo) {
    const size = 92
    const x = (WIDTH - size) / 2
    ctx.save()
    ctx.beginPath()
    ctx.arc(WIDTH / 2, y + size / 2, size / 2 + 4, 0, Math.PI * 2)
    ctx.fillStyle = '#f7f4ef'
    ctx.fill()
    ctx.beginPath()
    ctx.arc(WIDTH / 2, y + size / 2, size / 2, 0, Math.PI * 2)
    ctx.clip()
    ctx.drawImage(logo, x, y, size, size)
    ctx.restore()
    y += size + 18
  }

  ctx.fillStyle = '#f7f4ef'
  ctx.textAlign = 'center'
  ctx.font = '700 34px Segoe UI, system-ui, sans-serif'
  ctx.fillText(receipt.shop_name, WIDTH / 2, y + 8)
  y += 36
  if (receipt.shop_phone) {
    ctx.font = '20px Segoe UI, system-ui, sans-serif'
    ctx.fillText(receipt.shop_phone, WIDTH / 2, y)
    y += 28
  }
  if (receipt.header) {
    ctx.font = '18px Segoe UI, system-ui, sans-serif'
    ctx.fillText(receipt.header, WIDTH / 2, y)
  }

  let body = logo ? 290 : 220
  ctx.fillStyle = '#1c1917'
  ctx.textAlign = 'left'
  ctx.font = '700 22px Segoe UI, system-ui, sans-serif'
  ctx.fillText('RECEIPT', 48, body)
  ctx.textAlign = 'right'
  ctx.font = '600 22px Segoe UI, system-ui, sans-serif'
  ctx.fillText(receipt.receipt_number, WIDTH - 48, body)
  body += 16
  ctx.strokeStyle = '#e6dfd4'
  ctx.beginPath()
  ctx.moveTo(48, body)
  ctx.lineTo(WIDTH - 48, body)
  ctx.stroke()
  body += 36

  const rows: Array<[string, string]> = [
    ['Date', receipt.date ?? ''],
    ['Customer', receipt.customer ?? ''],
  ]
  if (receipt.job) {
    rows.push(['Job', receipt.job])
  }
  rows.push(['Method', receipt.method_label])
  if (receipt.reference) {
    rows.push(['Reference', receipt.reference])
  }
  if (receipt.note) {
    rows.push(['Note', receipt.note])
  }

  for (const [label, value] of rows) {
    ctx.textAlign = 'left'
    ctx.fillStyle = '#57534e'
    ctx.font = '18px Segoe UI, system-ui, sans-serif'
    ctx.fillText(label, 48, body)
    ctx.textAlign = 'right'
    ctx.fillStyle = '#1c1917'
    ctx.font = '600 18px Segoe UI, system-ui, sans-serif'
    ctx.fillText(fit(ctx, value, WIDTH - 220), WIDTH - 48, body)
    body += 36
  }

  body += 8
  ctx.fillStyle = '#fffcf8'
  roundRect(ctx, 40, body, WIDTH - 80, 92, 16)
  ctx.fill()
  ctx.textAlign = 'left'
  ctx.fillStyle = '#57534e'
  ctx.font = '16px Segoe UI, system-ui, sans-serif'
  ctx.fillText('Amount paid', 64, body + 34)
  ctx.fillStyle = '#1f3d34'
  ctx.font = '700 32px Segoe UI, system-ui, sans-serif'
  ctx.fillText(receipt.amount_label, 64, body + 70)
  body += 120

  const totals: Array<[string, string]> = []
  if (receipt.agreed_label) {
    totals.push(['Agreed', receipt.agreed_label])
  }
  if (receipt.paid_label) {
    totals.push(['Paid', receipt.paid_label])
  }
  if (receipt.outstanding_label) {
    totals.push(['Outstanding', receipt.outstanding_label])
  }
  for (const [label, value] of totals) {
    ctx.textAlign = 'left'
    ctx.fillStyle = '#57534e'
    ctx.font = '18px Segoe UI, system-ui, sans-serif'
    ctx.fillText(label, 48, body)
    ctx.textAlign = 'right'
    ctx.fillStyle = '#1c1917'
    ctx.font = '600 18px Segoe UI, system-ui, sans-serif'
    ctx.fillText(value, WIDTH - 48, body)
    body += 32
  }

  body += 36
  ctx.textAlign = 'center'
  ctx.fillStyle = '#1c1917'
  ctx.font = 'italic 42px "Segoe Script", "Brush Script MT", cursive'
  ctx.fillText(receipt.signature_name, WIDTH / 2, body + 20)
  body += 36
  ctx.strokeStyle = '#1c1917'
  ctx.beginPath()
  ctx.moveTo(WIDTH / 2 - 120, body)
  ctx.lineTo(WIDTH / 2 + 120, body)
  ctx.stroke()
  body += 28
  ctx.font = '16px Segoe UI, system-ui, sans-serif'
  ctx.fillStyle = '#57534e'
  ctx.fillText('Authorised signature', WIDTH / 2, body)

  if (receipt.footer) {
    body += 48
    ctx.font = '18px Segoe UI, system-ui, sans-serif'
    ctx.fillStyle = '#1c1917'
    ctx.fillText(receipt.footer, WIDTH / 2, body)
  }

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/png')
  })

  if (!blob) {
    throw new Error('Could not create the receipt image.')
  }

  return blob
}

export async function shareOrDownloadReceipt(
  blob: Blob,
  filename: string,
  text: string,
): Promise<'shared' | 'downloaded'> {
  const file = new File([blob], filename, { type: 'image/png' })

  if (navigator.canShare?.({ files: [file] })) {
    await navigator.share({ files: [file], text, title: filename })
    return 'shared'
  }

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
  return 'downloaded'
}

export function blobToBase64(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => {
      const result = String(reader.result ?? '')
      const comma = result.indexOf(',')
      resolve(comma >= 0 ? result.slice(comma + 1) : result)
    }
    reader.onerror = () => reject(new Error('Could not read the receipt image.'))
    reader.readAsDataURL(blob)
  })
}

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('Could not load the shop logo.'))
    image.src = src
  })
}

function fit(ctx: CanvasRenderingContext2D, value: string, max: number): string {
  if (ctx.measureText(value).width <= max) {
    return value
  }
  let text = value
  while (text.length > 1 && ctx.measureText(`${text}…`).width > max) {
    text = text.slice(0, -1)
  }
  return `${text}…`
}

function roundRect(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  width: number,
  height: number,
  radius: number,
) {
  ctx.beginPath()
  ctx.moveTo(x + radius, y)
  ctx.arcTo(x + width, y, x + width, y + height, radius)
  ctx.arcTo(x + width, y + height, x, y + height, radius)
  ctx.arcTo(x, y + height, x, y, radius)
  ctx.arcTo(x, y, x + width, y, radius)
  ctx.closePath()
}
