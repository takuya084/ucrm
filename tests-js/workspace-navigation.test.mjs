import test from 'node:test'
import assert from 'node:assert/strict'
import { workspaceNavigationState } from '../resources/js/Components/workspace-navigation-state.mjs'

let serial = 0
function fixture(saved = null) {
  const values = new Map()
  globalThis.window = { sessionStorage: { getItem: key => values.get(key) ?? saved, setItem: (key, value) => values.set(key, value) } }
  return { scope: [++serial, 1, 'admin'], values, defaults: { 'ホーム': true, '日々の業務': true, '設定': false } }
}
test('clicking a group persists only its own state and shares it across both navigation instances', () => {
  const f = fixture(), a = workspaceNavigationState(f.scope, f.defaults)
  a.setOpen('日々の業務', false)
  const b = workspaceNavigationState(f.scope, { ...f.defaults, '日々の業務': true })
  assert.equal(a, b)
  assert.deepEqual({ ...b.open }, { 'ホーム': true, '日々の業務': false, '設定': false })
  assert.equal(JSON.parse([...f.values.values()][0])['日々の業務'], false)
})
test('reloaded state accepts only boolean values for known groups', () => {
  const f = fixture('{"日々の業務":false,"設定":true,"ホーム":"false","unknown":true}')
  const state = workspaceNavigationState(f.scope, f.defaults)
  assert.deepEqual({ ...state.open }, { 'ホーム': true, '日々の業務': false, '設定': true })
})
test('malformed or unavailable storage does not prevent manual menu operation', () => {
  for (const input of ['bad-json', '[]', 'null']) {
    const f = fixture(input), state = workspaceNavigationState(f.scope, f.defaults)
    state.setOpen('設定', true)
    assert.equal(state.open['設定'], true)
  }
  const f = fixture()
  Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('blocked') } })
  const state = workspaceNavigationState(f.scope, f.defaults)
  state.setOpen('日々の業務', false)
  assert.equal(state.open['日々の業務'], false)
})
test('another user, facility or role receives independent state', () => {
  const f = fixture(), state = workspaceNavigationState(f.scope, f.defaults)
  state.setOpen('日々の業務', false)
  for (const scope of [[f.scope[0], 2, 'admin'], [f.scope[0] + 1000, 1, 'admin'], [f.scope[0], 1, 'staff']]) {
    assert.equal(workspaceNavigationState(scope, f.defaults).open['日々の業務'], true)
  }
})
