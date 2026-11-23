import {ref} from 'vue'
import {planShiftPaste, shiftRectangle, copyShiftRange} from './shiftClipboard.mjs'

export function useShiftClipboard({rows, cols, read, write, labels, times, canEdit}) {
  const anchor = ref(null), end = ref(null), status = ref('')
  let extending = false
  const cell = event => {
    const el = event.target.closest?.('[data-shift-cell]')
    return el ? {row: Number(el.dataset.clipRow), col: Number(el.dataset.clipCol), el} : null
  }
  const selected = (row, col) => {
    if (!anchor.value) return false
    const r = shiftRectangle(anchor.value, end.value)
    return row >= r.top && row <= r.bottom && col >= r.left && col <= r.right
  }
  const focus = event => {
    const p = cell(event)
    if (p && !extending) { anchor.value = p; end.value = p }
  }
  const pointer = event => {
    const p = cell(event)
    if (!p) return
    if (event.shiftKey && anchor.value) {
      event.preventDefault(); extending = true; end.value = p; p.el.focus(); extending = false
    } else { anchor.value = p; end.value = p }
  }
  const copy = event => {
    if (!cell(event) || !anchor.value || !event.clipboardData) return
    event.preventDefault()
    event.clipboardData.setData('text/plain', copyShiftRange(shiftRectangle(anchor.value, end.value), read))
    status.value = '選択範囲をコピーしました。'
  }
  const paste = event => {
    if (!cell(event) || !event.clipboardData) return
    event.preventDefault()
    if (!canEdit()) return
    const start = anchor.value ? {row: shiftRectangle(anchor.value, end.value).top, col: shiftRectangle(anchor.value, end.value).left} : cell(event)
    try {
      const changes = planShiftPaste({text: event.clipboardData.getData('text/plain'), start, rows: rows(), cols: cols(), read, labels: labels(), times})
      changes.forEach(c => write(c.row, c.col, c.value))
      status.value = '貼り付けました。「保存」で反映してください。'
    } catch (error) {
      status.value = error.message; window.alert(error.message)
    }
  }
  const keydown = event => {
    const p = cell(event)
    if (event.shiftKey && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key) && p) {
      event.preventDefault()
      if (!anchor.value) anchor.value = p
      const next = {...(end.value || p)}
      next.row = Math.max(0, Math.min(rows() - 1, next.row + (event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0)))
      next.col = Math.max(0, Math.min(cols() - 1, next.col + (event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0)))
      end.value = next
    }
    if (event.key === 'Escape') {anchor.value = p; end.value = p}
  }
  return {selected, status, focus, pointer, copy, paste, keydown}
}
