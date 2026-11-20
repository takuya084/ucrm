// Shared without modification by p-yoyaku and UCRM.
export const fields = ['pickup_time', 'pickup_location', 'pickup_staff_id', 'dropoff_time', 'dropoff_location', 'dropoff_staff_id']
export const headings = ['迎え時刻', '迎え場所', '迎え担当', '送り時刻', '送り場所', '送り担当']
const clone = value => JSON.parse(JSON.stringify(value))
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char])

export function parseTSV(text) {
  const rows = [[]]
  let cell = '', quoted = false
  for (let i = 0; i < text.length; i++) {
    const char = text[i]
    if (char === '"' && (quoted || cell === '')) {
      if (quoted && text[i + 1] === '"') { cell += '"'; i++ } else quoted = !quoted
    } else if (!quoted && (char === '\t' || char === '\n' || char === '\r')) {
      rows.at(-1).push(cell); cell = ''
      if (char !== '\t') { if (char === '\r' && text[i + 1] === '\n') i++; rows.push([]) }
    } else cell += char
  }
  if (quoted) throw new Error('貼り付けデータの引用符が閉じていません。')
  rows.at(-1).push(cell)
  if (rows.length > 1 && rows.at(-1).length === 1 && rows.at(-1)[0] === '') rows.pop()
  if (rows.length > 200 || rows.some(row => row.length > 6)) throw new Error('貼り付けは200行・6列以内にしてください。')
  return rows
}

export function staffLabels(staff) {
  return staff.map(person => ({ value: person.id, label: staff.filter(item => item.name === person.name).length > 1 ? `${person.name}（#${person.id}）` : person.name }))
}

export function resolveCell(field, text, row, config) {
  const value = String(text ?? '').trim().normalize('NFKC')
  if (value === '') return { value: field.endsWith('_staff_id') ? null : '', text: '', error: '' }
  if (field.endsWith('_time')) {
    const match = value.match(/^(\d{1,2}):(\d{2})(?::00)?$/)
    const time = match ? `${match[1].padStart(2, '0')}:${match[2]}` : value
    return config.times.includes(time) ? { value: time, text: time, error: '' } : { value, text, error: '候補にある5分刻みの時刻を入力してください。' }
  }
  const options = field.endsWith('_location')
    ? Object.entries(config.locations).map(([value, label]) => ({ value, label }))
    : staffLabels(config.staff)
  let matches = options.filter(option => option.label.normalize('NFKC') === value || String(option.value) === value)
  if (field.endsWith('_location') && row.location_aliases?.[text.trim()]) matches = options.filter(option => option.value === row.location_aliases[text.trim()])
  if (matches.length === 1) return { value: matches[0].value, text: matches[0].label, error: '' }
  return { value, text, error: field.endsWith('_location') ? '候補にある場所を選択してください。登録済みの学校・方面名も貼り付けできます。' : '候補にある職員を選択してください。' }
}

export function pasteRange(rows, startRow, startCol, matrix) {
  if (startRow + matrix.length > rows.length || matrix.some(line => startCol + line.length > fields.length)) throw new Error('貼り付け範囲が表からはみ出します。必要な児童を先に追加してください。')
  const result = clone(rows)
  matrix.forEach((line, offset) => line.forEach((text, col) => { result[startRow + offset][fields[startCol + col]] = text }))
  return result
}

// Keep the current value when opening the complete candidate list.
export function bindCandidatePicker(input, values) {
  const button = document.createElement('button')
  button.type = 'button'; button.className = 'tg-picker-toggle'; button.textContent = '▾'
  button.setAttribute('aria-label', input.getAttribute('aria-label') + 'の候補をすべて表示')
  button.disabled = input.disabled
  input.after(button)
  const list = document.createElement('div')
  list.id = 'tg-options-' + Math.random().toString(36).slice(2)
  list.className = 'tg-options'; list.setAttribute('role', 'listbox'); list.hidden = true
  document.body.append(list)
  input.removeAttribute('list'); input.setAttribute('role', 'combobox')
  input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-controls', list.id)
  button.setAttribute('aria-controls', list.id); button.setAttribute('aria-haspopup', 'listbox')
  list.setAttribute('aria-label', input.getAttribute('aria-label') + 'の候補')
  let items = [], index = -1
  const close = () => {
    list.hidden = true; input.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-expanded', 'false')
    input.removeAttribute('aria-activedescendant')
  }
  const highlight = () => {
    list.querySelectorAll('[role=option]').forEach((option, i) => option.setAttribute('aria-selected', String(i === index)))
    if (index >= 0) { input.setAttribute('aria-activedescendant', list.id + '-' + index); list.children[index]?.scrollIntoView({ block: 'nearest' }) }
  }
  const choose = value => {
    input.value = value; close()
    input.dispatchEvent(new Event('input', { bubbles: true }))
    input.dispatchEvent(new Event('change', { bubbles: true })); close(); input.focus({ preventScroll: true })
  }
  const position = () => {
    const rect = input.parentElement.getBoundingClientRect()
    const width = Math.min(Math.max(rect.width, 220), window.innerWidth - 16)
    list.style.width = width + 'px'; list.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px'
    const below = window.innerHeight - rect.bottom - 8
    list.style.maxHeight = Math.max(64, Math.min(240, Math.max(below, rect.top - 8))) + 'px'
    list.style.top = below >= Math.min(240, items.length * 44 + 12) || below >= rect.top ? rect.bottom + 4 + 'px' : 'auto'
    list.style.bottom = list.style.top === 'auto' ? window.innerHeight - rect.top + 4 + 'px' : 'auto'
  }
  const open = (all = true) => {
    if (input.disabled) return
    const query = all ? '' : input.value.trim().normalize('NFKC').toLowerCase()
    items = values().filter(value => value.normalize('NFKC').toLowerCase().includes(query)); index = -1
    list.replaceChildren()
    items.forEach((value, i) => {
      const option = document.createElement('div')
      option.id = list.id + '-' + i; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false')
      option.textContent = value; option.onpointerdown = event => event.preventDefault(); option.onclick = () => choose(value)
      list.append(option)
    })
    if (!items.length) { const empty = document.createElement('p'); empty.textContent = '候補がありません'; list.append(empty) }
    position()
    list.hidden = false; input.setAttribute('aria-expanded', 'true'); button.setAttribute('aria-expanded', 'true')
  }
  button.onclick = () => { const wasOpen = !list.hidden; input.focus({ preventScroll: true }); if (wasOpen) close(); else open(true) }
  const typed = () => open(false)
  const key = event => {
    if (event.shiftKey) return // Preserve spreadsheet range selection.
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault(); event.stopImmediatePropagation()
      if (list.hidden) open(true)
      index = !items.length ? -1 : index < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; highlight()
    } else if (event.key === 'Enter' && !list.hidden && index >= 0) {
      event.preventDefault(); choose(items[index])
    } else if (event.key === 'Escape' || event.key === 'Tab') close()
  }
  const outside = event => { if (!list.hidden && event.target !== input && event.target !== button && !list.contains(event.target)) close() }
  const blur = event => { if (event.relatedTarget !== button) close() }
  input.addEventListener('input', typed); input.addEventListener('keydown', key)
  input.addEventListener('blur', blur); document.addEventListener('pointerdown', outside)
  const scrolled = event => { if (!list.hidden && !list.contains(event.target)) position() }
  window.addEventListener('resize', scrolled); window.addEventListener('scroll', scrolled, true)
  close()
  return () => {
    close(); list.remove(); button.remove(); input.removeEventListener('input', typed); input.removeEventListener('keydown', key)
    input.removeEventListener('blur', blur); document.removeEventListener('pointerdown', outside)
    window.removeEventListener('resize', scrolled); window.removeEventListener('scroll', scrolled, true)
  }
}

export function mountTransportGrid(root, options) {
  let config, rows = [], original = new Map(), selected = new Set(), deleted = new Set(), errors = {}, anchor = null, end = null, busy = false, uncertain = false
  let message = '', disposed = false, pickers = []
  const disclosures = { guide: false, tools: false }
  const disclosure = (key, title, content) => `<div class="tg-disclosure"><button type="button" data-disclosure="${key}" aria-expanded="${disclosures[key]}" aria-controls="${uid}-${key}-panel" id="${uid}-${key}-trigger">${title}<span aria-hidden="true">⌄</span></button><div id="${uid}-${key}-panel" role="region" aria-labelledby="${uid}-${key}-trigger" data-disclosure-panel="${key}" class="tg-disclosure-panel ${disclosures[key] ? 'is-open' : ''}" aria-hidden="${!disclosures[key]}" ${disclosures[key] ? '' : 'inert'}><div class="tg-disclosure-content"><div class="tg-disclosure-body">${content}</div></div></div></div>`
  const clearPickers = () => { pickers.forEach(destroy => destroy()); pickers = [] }
  const editable = options.canManage !== false
  const uid = `tg-${Math.random().toString(36).slice(2)}`
  const dirty = row => !original.has(row.user_id) || fields.some(field => row[field] !== original.get(row.user_id)[field]) || deleted.has(row.user_id)
  const hasChanges = () => rows.some(dirty)
  const changed = () => rows.filter(dirty)
  const status = () => {
    const count = changed().length
    root.querySelector('[data-count]').textContent = count ? `未保存 ${count}件` : '保存済み'
    root.querySelector('[data-save]').disabled = busy || uncertain || !count
  }
  const cell = (row, col) => resolveCell(fields[col], rows[row][fields[col]], rows[row], config)
  const readText = (row, field) => {
    if (field.endsWith('_location')) return config.locations[row[field]] ?? row[field] ?? ''
    if (field.endsWith('_staff_id')) return staffLabels(config.staff).find(person => String(person.value) === String(row[field]))?.label ?? row[field] ?? ''
    return row[field] ?? ''
  }
  const bounds = () => anchor && end ? { top: Math.min(anchor.row, end.row), bottom: Math.max(anchor.row, end.row), left: Math.min(anchor.col, end.col), right: Math.max(anchor.col, end.col) } : null
  const paintSelection = () => {
    const box = bounds()
    root.querySelectorAll('[data-cell]').forEach(input => input.classList.toggle('tg-range', !!box && +input.dataset.row >= box.top && +input.dataset.row <= box.bottom && +input.dataset.col >= box.left && +input.dataset.col <= box.right))
  }
  const validate = () => {
    errors = {}
    rows.forEach((row, i) => {
      if (deleted.has(row.user_id) || !dirty(row)) return
      fields.forEach((field, col) => { const result = cell(i, col); if (result.error) errors[`${i}.${field}`] = result.error })
      if (!row.pickup_time && !row.dropoff_time) errors[`${i}.pickup_time`] = '迎え・送りのどちらかの時刻を入力してください。'
      for (const direction of ['pickup', 'dropoff']) {
        if (!!row[direction + '_time'] !== !!row[direction + '_location']) errors[`${i}.${direction}_location`] = '時刻と場所をセットで入力してください。'
        const location = resolveCell(direction + '_location', row[direction + '_location'], row, config).value
        if (row[direction + '_staff_id'] && (!row[direction + '_time'] || location === 'parents')) errors[`${i}.${direction}_staff_id`] = '親送迎・時刻なしの方向には担当を指定できません。'
      }
    })
    return !Object.keys(errors).length
  }
  const render = () => {
    if (disposed) return
    clearPickers()
    root.innerHTML = `<section class="tg" aria-label="送迎一覧の一括編集">
      <div class="tg-toolbar"><strong>送迎一覧</strong><span data-count role="status"></span><button type="button" data-reload>最新の一覧を確認</button></div>
      ${disclosure('guide', '入力・コピペの使い方', '<p class="tg-help">▼で全候補を表示。文字入力で絞り込み。Excelの複数セルを貼り付けできます。範囲コピーはShift＋クリックで選択します。</p><p class="tg-help">時刻は設定範囲内の5分刻み、場所・担当は候補にあるものだけ保存できます。行の削除は当日の予約の取り消しです。</p>')}
      <p role="status" class="tg-message">${escape(message)}</p>
      ${editable ? disclosure('tools', '予約の追加・削除／一括変更', `<div class="tg-toolbar"><label>追加する児童 <select data-child aria-label="追加する児童"><option value="">選択してください</option>${config.rows.filter(person => !rows.some(row => row.user_id === person.user_id)).map(person => `<option value="${person.user_id}">${escape(person.name)}</option>`).join('')}</select></label><button type="button" data-add>行を追加</button><button type="button" data-add-all>未予約の児童をまとめて追加</button><button type="button" data-delete>選択した予約を削除</button><button type="button" data-restore>削除を取り消す</button></div>
      <div class="tg-toolbar"><label>一括変更する列 <select data-bulk-field>${fields.map((field, i) => `<option value="${i}">${headings[i]}</option>`).join('')}</select></label><label>入力値 <span class="tg-picker"><input data-bulk-value aria-label="一括変更の入力値" list="${uid}-bulk" autocomplete="off"></span></label><datalist id="${uid}-bulk"></datalist><button type="button" data-apply>選択した児童に適用</button></div>`) : ''}
      <div class="tg-scroll"><table><thead><tr><th scope="col">選択${editable ? '<input type="checkbox" data-select-all aria-label="表示中の児童をすべて選択">' : ''}</th><th scope="col">児童</th>${headings.map(name => `<th scope="col">${name}</th>`).join('')}</tr></thead><tbody>${rows.map((row, i) => `<tr class="${deleted.has(row.user_id) ? 'tg-deleted' : ''}"><td><input type="checkbox" data-select="${row.user_id}" aria-label="${escape(row.name)}を選択" ${selected.has(row.user_id) ? 'checked' : ''} ${!editable ? 'disabled' : ''}></td><th scope="row">${escape(row.name)}${deleted.has(row.user_id) ? '<small>保存時に予約を削除</small>' : ''}</th>${fields.map((field, col) => {
        const error = errors[`${i}.${field}`]
        return `<td><div class="tg-picker"><input data-cell data-row="${i}" data-col="${col}" aria-label="${escape(row.name)} ${headings[col]}" aria-invalid="${!!error}" aria-describedby="${uid}-${i}-${col}-error" value="${escape(readText(row, field))}" list="${uid}-${field}" autocomplete="off" ${!editable || deleted.has(row.user_id) || busy || uncertain ? 'disabled' : ''} class="${dirty(row) ? 'tg-dirty' : ''}"></div><small id="${uid}-${i}-${col}-error">${escape(error || '')}</small></td>`
      }).join('')}</tr>`).join('')}</tbody></table></div>
      ${fields.map(field => `<datalist id="${uid}-${field}">${(field.endsWith('_time') ? config.times : field.endsWith('_location') ? Object.values(config.locations) : staffLabels(config.staff).map(person => person.label)).map(value => `<option value="${escape(value)}"></option>`).join('')}</datalist>`).join('')}
      ${editable ? '<div class="tg-toolbar"><button type="button" data-save class="tg-primary">変更をまとめて保存</button></div>' : '<p>閲覧専用です。変更は管理者・児発管に依頼してください。</p>'}
    </section>`
    root.querySelectorAll('[data-disclosure]').forEach(button => {
      button.onclick = () => {
        const key = button.dataset.disclosure
        disclosures[key] = !disclosures[key]
        const panel = root.querySelector(`[data-disclosure-panel="${key}"]`)
        if (!disclosures[key]) { if (panel.contains(document.activeElement)) button.focus(); panel.querySelectorAll('input').forEach(input => input.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true}))) }
        button.setAttribute('aria-expanded', String(disclosures[key]))
        panel.classList.toggle('is-open', disclosures[key]); panel.setAttribute('aria-hidden', String(!disclosures[key])); panel.inert = !disclosures[key]
      }
    })
    root.querySelector('[data-reload]').onclick = () => { if (!hasChanges() || confirm('未保存の入力を破棄して最新の一覧を読み込みますか？')) load() }
    if (!editable) return
    status(); paintSelection()
    if (busy || uncertain) root.querySelectorAll('button, input, select').forEach(control => { control.disabled = busy || !control.hasAttribute('data-reload') })
    root.querySelector('[data-add]').onclick = () => {
      const id = +root.querySelector('[data-child]').value
      const person = config.rows.find(row => row.user_id === id)
      if (!person) return
      rows.push(clone(person)); render()
    }
    root.querySelector('[data-add-all]').onclick = () => {
      rows.push(...clone(config.rows.filter(person => !rows.some(row => row.user_id === person.user_id))))
      anchor = end = null; render()
    }
    root.querySelector('[data-delete]').onclick = () => {
      if (!selected.size) { message = '削除する児童にチェックを付けてください。'; render(); return }
      selected.forEach(id => { if (original.get(id)?.exists) deleted.add(id) })
      rows = rows.filter(row => original.get(row.user_id)?.exists || !selected.has(row.user_id)); selected.clear(); anchor = end = null; render()
    }
    root.querySelector('[data-restore]').onclick = () => { deleted.clear(); render() }
    let bulkPicker
    const bulkChoices = () => {
      const field = fields[+root.querySelector('[data-bulk-field]').value]
      const values = field.endsWith('_time') ? config.times : field.endsWith('_location') ? Object.values(config.locations) : staffLabels(config.staff).map(person => person.label)
      bulkPicker?.()
      const input = root.querySelector('[data-bulk-value]')
      bulkPicker = bindCandidatePicker(input, () => values); pickers.push(bulkPicker)
    }
    root.querySelector('[data-bulk-field]').onchange = bulkChoices; bulkChoices()
    root.querySelector('[data-apply]').onclick = () => {
      if (!selected.size) { message = '変更する児童にチェックを付けてください。'; render(); return }
      const field = fields[+root.querySelector('[data-bulk-field]').value], text = root.querySelector('[data-bulk-value]').value
      rows.forEach(row => { if (selected.has(row.user_id) && !deleted.has(row.user_id)) row[field] = text })
      validate(); message = '選択した児童に適用しました。まだ保存していません。'; render()
    }
    root.querySelector('[data-save]').onclick = save
    root.querySelector('[data-select-all]').onchange = event => {
      selected.clear(); if (event.target.checked) rows.forEach(row => selected.add(row.user_id))
      root.querySelectorAll('[data-select]').forEach(input => { input.checked = selected.has(+input.dataset.select) })
    }
    root.querySelectorAll('[data-select]').forEach(input => { input.onchange = () => { const id = +input.dataset.select; if (input.checked) selected.add(id); else selected.delete(id) } })
    root.querySelectorAll('[data-cell]').forEach(input => {
      const row = +input.dataset.row, col = +input.dataset.col
      input.onclick = event => { if (!event.shiftKey || !anchor) anchor = { row, col }; end = { row, col }; paintSelection() }
      input.onfocus = () => { if (!anchor) anchor = end = { row, col } }
      input.oninput = () => { rows[row][fields[col]] = input.value; input.classList.add('tg-dirty'); status() }
      input.onchange = () => { const result = resolveCell(fields[col], input.value, rows[row], config); if (!result.error) { rows[row][fields[col]] = result.value; input.value = result.text } validate(); showErrors(); status(); root.querySelectorAll(`[data-row="${row}"]`).forEach(control => control.classList.toggle('tg-dirty', dirty(rows[row]))) }
      input.onpaste = event => {
        event.preventDefault()
        try {
          const matrix = parseTSV(event.clipboardData.getData('text/plain'))
          // Never write into a row marked for deletion.
          if (matrix.some((line, offset) => deleted.has(rows[row + offset]?.user_id))) throw new Error('削除予定の行には貼り付けできません。')
          rows = pasteRange(rows, row, col, matrix)
          validate(); message = '貼り付けました。まだ保存していません。'; render()
          root.querySelector(`[data-row="${row}"][data-col="${col}"]`)?.focus()
        } catch (error) { message = error.message; root.querySelector('.tg-message').textContent = message }
      }
      input.oncopy = event => {
        const box = bounds() ?? { top: row, bottom: row, left: col, right: col }
        if (box.top === box.bottom && box.left === box.right && input.selectionEnd > input.selectionStart) return
        const text = rows.slice(box.top, box.bottom + 1).map(item => fields.slice(box.left, box.right + 1).map(field => readText(item, field)).join('\t')).join('\n')
        event.preventDefault(); event.clipboardData.setData('text/plain', text)
      }
      pickers.push(bindCandidatePicker(input, () => fields[col].endsWith('_time') ? config.times : fields[col].endsWith('_location') ? Object.values(config.locations) : staffLabels(config.staff).map(person => person.label)))
      input.onkeydown = event => {
        const moves = { ArrowDown: [1, 0], ArrowUp: [-1, 0], ArrowRight: [0, 1], ArrowLeft: [0, -1] }
        if (!event.shiftKey || !moves[event.key]) return
        event.preventDefault(); anchor ??= { row, col }
        const next = { row: Math.max(0, Math.min(rows.length - 1, row + moves[event.key][0])), col: Math.max(0, Math.min(5, col + moves[event.key][1])) }
        end = next; root.querySelector(`[data-row="${next.row}"][data-col="${next.col}"]`)?.focus(); paintSelection()
      }
    })
  }
  const showErrors = () => {
    root.querySelectorAll('[data-cell]').forEach(input => {
      const error = errors[`${input.dataset.row}.${fields[+input.dataset.col]}`] || ''
      input.setAttribute('aria-invalid', String(!!error)); document.getElementById(input.getAttribute('aria-describedby')).textContent = error
    })
  }
  const load = async () => {
    if (busy) return
    busy = true
    clearPickers()
    root.innerHTML = '<p role="status">送迎一覧を読み込んでいます。</p>'
    try {
      const data = await options.load()
      if (disposed) return
      if (!data || !Array.isArray(data.rows) || !Array.isArray(data.times) || !Array.isArray(data.staff) || !data.locations) throw new Error('送迎一覧を読み込めません。連携設定と両アプリの更新状況を確認してください。')
      config = data; rows = clone(data.rows.filter(row => row.exists)); original = new Map(data.rows.map(row => [row.user_id, clone(row)]))
      selected.clear(); deleted.clear(); errors = {}; anchor = end = null; uncertain = false; message = ''; busy = false; render(); return true
    } catch (error) { busy = false; root.innerHTML = `<p role="alert">${escape(error.message)}</p><button type="button">再読み込み</button>`; root.querySelector('button').onclick = load; return false }
  }
  const save = async () => {
    if (busy || uncertain || !hasChanges()) return
    if (!validate()) { message = '入力に誤りがあります。赤いセルを修正してください。すべて未保存です。'; render(); return }
    const batch = changed()
    if (batch.filter(row => deleted.has(row.user_id)).length && !confirm(`${batch.filter(row => deleted.has(row.user_id)).length}件の当日予約を削除します。ほかの変更とまとめて保存しますか？`)) return
    busy = true; message = '保存しています。'; render()
    try {
      await options.save({ date: options.date, rows: batch.map(row => {
        const value = { user_id: row.user_id, snapshot: row.snapshot, action: deleted.has(row.user_id) ? 'delete' : 'save' }
        fields.forEach(field => { value[field] = resolveCell(field, readText(row, field), row, config).value })
        return value
      }) })
      busy = false; const loaded = await load(); if (loaded) { message = '変更をまとめて保存しました。'; render() } else { uncertain = true; root.querySelector('[role=alert]').textContent = '保存されましたが一覧を再取得できません。再読み込みで確認してください。' }
    } catch (error) {
      busy = false
      if (error.errors) {
        errors = {}
        Object.entries(error.errors).forEach(([key, messages]) => {
          const match = key.match(/^rows\.(\d+)\.(.+)$/)
          if (match) { const row = rows.findIndex(item => item.user_id === batch[+match[1]]?.user_id); errors[`${row}.${match[2]}`] = [].concat(messages).join(' ') }
        })
        message = 'すべて未保存です。入力内容は残っています。' + Object.values(error.errors).flat().join(' ')
      } else { uncertain = true; message = error.message + ' 入力内容を控え、「最新の一覧を確認」で保存結果を確認してください。' }
      render()
    }
  }
  const unload = event => { if (hasChanges()) { event.preventDefault(); event.returnValue = '' } }
  window.addEventListener('beforeunload', unload)
  load()
  return () => { disposed = true; clearPickers(); window.removeEventListener('beforeunload', unload); root.innerHTML = '' }
}

export async function gridRequest(url, method = 'GET', payload) {
  const xsrf = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='))?.slice(11)
  const csrfHeaders = xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
  const response = await fetch(url, { method, credentials: 'same-origin', cache: 'no-store', headers: {
    Accept: 'application/json', 'Content-Type': 'application/json',
    ...csrfHeaders,
  }, ...(payload ? { body: JSON.stringify(payload) } : {}) })
  const data = await response.json().catch(() => null)
  if (!response.ok) { const error = new Error(response.status === 419 ? '画面の認証情報が期限切れです。ページを再読み込みしてから入力し直してください。' : '通信または保存に失敗しました。'); if (response.status === 422) error.errors = data?.errors; throw error }
  if (method !== 'GET' && data?.ok !== true) throw new Error('保存結果を確認できません。')
  return data
}

if (typeof document !== 'undefined') {
  document.querySelectorAll('[data-transport-grid]').forEach(root => {
    const params = new URLSearchParams({ date: root.dataset.date, business_id: root.dataset.business })
    mountTransportGrid(root, { date: root.dataset.date, load: () => gridRequest(root.dataset.url + '?' + params),
      save: payload => gridRequest(root.dataset.url, 'PUT', { ...payload, business_id: +root.dataset.business }) })
  })
}
