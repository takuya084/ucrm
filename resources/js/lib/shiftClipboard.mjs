export function shiftTimes() {
  return Array.from({length: 68}, (_, i) => `${String(6 + Math.floor(i / 4)).padStart(2, '0')}:${String(i % 4 * 15).padStart(2, '0')}`)
}
export function parseShiftTsv(text) {
  if (typeof text !== 'string' || text.length > 200000) throw new Error('貼り付ける量が多すぎます。')
  const lines = text.replace(/\r\n?/g, '\n').replace(/\n$/, '').split('\n')
  const cells = lines.map(line => line.split('\t'))
  if (cells.some(row => row.length !== cells[0].length)) throw new Error('行ごとの列数が異なります。表の範囲を選び直してください。')
  return cells
}
export function planShiftPaste({text, start, rows, cols, read, labels, times}) {
  const cells = parseShiftTsv(text)
  if (start.row < 0 || start.col < 0 || start.row + cells.length > rows || start.col + cells[0].length > cols) {
    throw new Error('貼り付ける範囲が表からはみ出します。変更していません。')
  }
  const known = new Set(labels.map(label => label.name)), allowedTimes = new Set(times)
  const off = new Set(labels.filter(label => label.is_off).map(label => label.name))
  const pending = new Map()
  const put = (row, col, value) => pending.set(`${row}:${col}`, {row, col, value})
  cells.forEach((line, ri) => line.forEach((raw, ci) => {
    const row = start.row + ri, col = start.col + ci
    let value = raw.trim()
    const where = `${ri + 1}行${ci + 1}列目`
    if (col % 2 === 0) {
      if (!known.has(value) && ['―', '—'].includes(value)) value = ''
      if (value && !known.has(value)) throw new Error(`${where}：「${value}」は未登録の勤務区分です。変更していません。`)
    } else {
      if (['--:--', '―', '—'].includes(value)) value = ''
      if (/^\d{1,2}:\d{2}$/.test(value)) value = value.padStart(5, '0')
      if (value && !allowedTimes.has(value)) throw new Error(`${where}：「${value}」は選択できない時刻です。06:00〜22:45の15分刻みで指定してください。変更していません。`)
      value = value || null
    }
    put(row, col, value)
  }))
  const touched = new Set([...pending.values()].map(cell => `${cell.row}:${cell.col - cell.col % 2}`))
  for (const key of touched) {
    const [row, col] = key.split(':').map(Number)
    const type = pending.get(key)?.value ?? read(row, col)
    const time = pending.get(`${row}:${col + 1}`)
    if (off.has(type)) {
      if (time?.value) throw new Error('休み系の勤務区分には開始時刻を貼り付けられません。変更していません。')
      put(row, col + 1, null)
    }
  }
  return [...pending.values()]
}
export function shiftRectangle(a, b = a) {
  return {top: Math.min(a.row, b.row), left: Math.min(a.col, b.col), bottom: Math.max(a.row, b.row), right: Math.max(a.col, b.col)}
}
export function copyShiftRange(rect, read) {
  return Array.from({length: rect.bottom - rect.top + 1}, (_, ri) =>
    Array.from({length: rect.right - rect.left + 1}, (_, ci) => read(rect.top + ri, rect.left + ci) ?? '').join('\t')).join('\n')
}
