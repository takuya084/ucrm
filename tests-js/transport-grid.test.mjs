import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
const source = await readFile(new URL('../public/transport-grid/grid.js', import.meta.url), 'utf8')
const { parseTSV, resolveCell, pasteRange, staffLabels } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'))
const config = { times: ['10:00', '14:30', '18:00'], locations: { school: '学校', home: '自宅', parents: '親送迎', others: 'その他' }, staff: [{ id: 7, name: '職員A' }] }

test('Excel TSV supports CRLF, empty cells and quoted values', () => {
  assert.deepEqual(parseTSV('14:30\t学校\t職員A\r\n18:00\t"自宅"\t\r\n'), [['14:30', '学校', '職員A'], ['18:00', '自宅', '']])
  assert.throws(() => parseTSV('"未完了'))
})
test('time paste normalizes fullwidth characters without rounding invalid minutes', () => {
  assert.equal(resolveCell('pickup_time', '１４：３０', {}, config).value, '14:30')
  assert.equal(resolveCell('pickup_time', '14:30:00', {}, config).error, '')
  for (const time of ['14:32', '09:00', '14:30:30', '24:00']) assert.ok(resolveCell('pickup_time', time, {}, config).error)
})
test('locations and staff must match registered candidates', () => {
  assert.equal(resolveCell('pickup_location', '登録学校', { location_aliases: { 登録学校: 'school' } }, config).value, 'school')
  assert.ok(resolveCell('pickup_location', '未登録公園', {}, config).error)
  assert.equal(resolveCell('pickup_staff_id', '職員A', {}, config).value, 7)
  assert.ok(resolveCell('pickup_staff_id', '他事業所の職員', {}, config).error)
  assert.equal(resolveCell('pickup_staff_id', '', {}, config).value, null)
})
test('duplicate staff names require their displayed disambiguated label', () => {
  const data = { ...config, staff: [{ id: 1, name: '同名' }, { id: 2, name: '同名' }] }
  assert.ok(resolveCell('pickup_staff_id', '同名', {}, data).error)
  assert.equal(resolveCell('pickup_staff_id', staffLabels(data.staff)[1].label, {}, data).value, 2)
})
test('range paste is bounded and does not mutate data on failure', () => {
  const rows = [{ user_id: 1 }, { user_id: 2 }]
  assert.equal(pasteRange(rows, 0, 0, [['14:30'], ['18:00']])[1].pickup_time, '18:00')
  assert.throws(() => pasteRange(rows, 1, 0, [['14:30'], ['18:00']]))
  assert.throws(() => pasteRange(rows, 0, 7, [['x', 'y']]))
  assert.deepEqual(rows, [{ user_id: 1 }, { user_id: 2 }])
})


test('requests use the current XSRF cookie after login instead of a stale head meta; 419 is not retried', async () => {
  const { gridRequest } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'))
  const oldDocument = globalThis.document, oldFetch = globalThis.fetch
  let requests = []
  try {
    globalThis.document = { cookie: 'XSRF-TOKEN=fresh%2Bcookie%3D', querySelector: () => ({ content: 'stale-meta' }) }
    globalThis.fetch = async (url, options) => { requests.push(options); return { ok: true, json: async () => ({ ok: true }) } }
    await gridRequest('/grid', 'PUT', { rows: [] })
    assert.equal(requests[0].headers['X-XSRF-TOKEN'], 'fresh+cookie=')
    assert.equal(requests[0].headers['X-CSRF-TOKEN'], undefined)
    globalThis.document.cookie = ''
    await gridRequest('/grid', 'PUT', { rows: [] })
    assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'stale-meta')
    globalThis.fetch = async () => { requests.push(null); return { ok: false, status: 419, json: async () => ({}) } }
    await assert.rejects(() => gridRequest('/grid', 'PUT', { rows: [] }), /認証情報が期限切れ/)
    assert.equal(requests.length, 3)
  } finally { globalThis.document = oldDocument; globalThis.fetch = oldFetch }
})

test("second assignee and school-name paste retain registered values",()=>{assert.equal(resolveCell("pickup_assistant_staff_id", "職員A", {}, {times:[],locations:{school:"学校"},staff:[{id:2,name:"職員A"}]}).value,2);assert.equal(resolveCell("pickup_location","第一小学校",{school_name:"第一小学校",location_aliases:{"第一小学校":"school"}},{times:[],locations:{school:"学校"},staff:[]}).text,"第一小学校")})

test('missing-school display remains a valid unchanged location when saving a time edit',()=>{assert.equal(resolveCell('pickup_location','学校（名称未登録）',{school_name:null},{times:[],locations:{school:'学校'},staff:[]}).value,'school')})
