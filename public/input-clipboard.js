/* Shared byte-for-byte by p-yoyaku and UCRM. Registered choices only. */
;(function (root) {
  'use strict'
  const textOf = option => String(option.label ?? option.textContent ?? option.value ?? '').trim()
  const enabled = option => !option.disabled && !option.hidden && !option.parentElement?.disabled
  const key = value => {
    const text = String(value).trim().normalize('NFKC')
    const time = text.match(/^(\d{1,2}):(\d{2})(?::00)?$/)
    return time ? time[1].padStart(2, '0') + ':' + time[2] : text
  }
  function parse(text) {
    if (typeof text !== 'string' || text.length > 200000) throw new Error('貼り付ける量が多すぎます。')
    const rows = [[]]; let value = '', quoted = false
    for (let i = 0; i < text.length; i++) {
      const char = text[i]
      if (char === '"' && (quoted || value === '')) {
        if (quoted && text[i + 1] === '"') { value += '"'; i++ } else quoted = !quoted
      } else if (!quoted && ['\t', '\r', '\n'].includes(char)) {
        rows[rows.length - 1].push(value); value = ''
        if (char !== '\t') { if (char === '\r' && text[i + 1] === '\n') i++; rows.push([]) }
      } else value += char
    }
    if (quoted) throw new Error('貼り付けデータの引用符が閉じていません。')
    rows[rows.length - 1].push(value)
    if (rows.length > 1 && rows[rows.length - 1].length === 1 && rows[rows.length - 1][0] === '') rows.pop()
    return rows
  }
  function resolve(options, raw) {
    let matches = options.filter(o => textOf(o) === raw.trim())
    if (matches.length === 1) return matches[0]
    const qualified = options.filter(o => `${textOf(o)}（#${o.value}）` === raw.trim())
    if (qualified.length === 1) return qualified[0]
    if (!matches.length) matches = options.filter(o => key(textOf(o)) === key(raw))
    if (matches.length > 1) throw new Error('同じ表示名の候補が複数あります。番号付きでコピーするか、プルダウンで選んでください。')
    if (!matches.length) throw new Error('貼り付けた値は、この欄の選択肢にありません。入力内容は変更していません。')
    return matches[0]
  }
  function selectPlan(select, text) {
    const rows = parse(text)
    if (rows.some(row => row.length !== 1) || (!select.multiple && rows.length !== 1)) throw new Error('この欄には1つの値を貼り付けてください。表の範囲貼り付けは対応する表内で行えます。')
    const options = Array.from(select.options).filter(enabled)
    if (select.multiple && rows.length === 1 && rows[0][0].trim() === '') return []
    return rows.map(row => {
      if (row[0].trim() === '') {
        const empty = options.filter(o => o.value === '')
        if (empty.length === 1) return empty[0]
      }
      return resolve(options, row[0])
    })
  }
  function copySelection(select) {
    const all = Array.from(select.options)
    return all.filter(o => o.selected).map(o => {
      const label = textOf(o)
      const value = all.filter(c => textOf(c) === label).length > 1 ? `${label}（#${o.value}）` : label
      return /[\t\r\n"]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value
    }).join('\n')
  }
  function install(doc) {
    if (doc.__registeredChoiceClipboard) return
    doc.__registeredChoiceClipboard = true
    const target = event => event.target?.closest?.('select, input[list], input[role="combobox"]')
    const candidates = input => {
      if (typeof input.clipboardChoices === 'function') return input.clipboardChoices().map(value => ({label: value, value}))
      return input.list ? Array.from(input.list.options).filter(enabled).map(o => ({label: o.value, value: o.value})) : null
    }
    doc.addEventListener('copy', event => {
      if (event.defaultPrevented || !event.clipboardData) return
      const el = target(event); if (!el) return
      if (el.tagName !== 'SELECT' && (!candidates(el) || el.selectionEnd > el.selectionStart)) return
      event.preventDefault()
      event.clipboardData.setData('text/plain', el.tagName === 'SELECT' ? copySelection(el) : el.value)
    })
    doc.addEventListener('paste', event => {
      if (event.defaultPrevented || !event.clipboardData) return
      const el = target(event); if (!el) return
      const choices = el.tagName === 'SELECT' ? null : candidates(el)
      if (el.tagName !== 'SELECT' && !choices) return
      event.preventDefault()
      if (el.disabled || el.readOnly || el.matches(':disabled') || el.getAttribute('aria-disabled') === 'true') return
      try {
        if (el.tagName === 'SELECT') {
          const selection = new Set(selectPlan(el, event.clipboardData.getData('text/plain')))
          Array.from(el.options).forEach(o => { o.selected = selection.has(o) })
        } else {
          const rows = parse(event.clipboardData.getData('text/plain'))
          if (rows.length !== 1 || rows[0].length !== 1) throw new Error('この欄には1つの値を貼り付けてください。')
          el.value = rows[0][0].trim() === '' ? '' : resolve(choices, rows[0][0]).value
        }
        el.dispatchEvent(new Event('input', {bubbles: true}))
        el.dispatchEvent(new Event('change', {bubbles: true}))
      } catch (error) { root.alert(error.message) }
    })
  }
  root.RegisteredChoiceClipboard = {install, parse, resolve, selectPlan, copySelection}
  if (root.document) install(root.document)
})(globalThis)
