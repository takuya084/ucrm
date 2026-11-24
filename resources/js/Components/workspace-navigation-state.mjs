import { reactive } from 'vue'

// Both the desktop sidebar and mobile drawer share the same state in this tab.
const states = new Map()
export function workspaceNavigationState(scope, defaults) {
  const key = 'ucrm:workspace-navigation:v1:' + JSON.stringify(scope)
  if (states.has(key)) return states.get(key)
  let storage, saved = {}
  try {
    storage = window.sessionStorage
    const parsed = JSON.parse(storage.getItem(key) ?? '{}')
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) saved = parsed
  } catch { /* Navigation also works when browser storage is unavailable. */ }
  const open = reactive(Object.fromEntries(Object.entries(defaults).map(([label, initial]) =>
    [label, typeof saved[label] === 'boolean' ? saved[label] : initial])))
  const persist = () => { try { storage?.setItem(key, JSON.stringify(open)) } catch {} }
  const state = {
    open,
    setOpen(label, value) {
      if (!Object.hasOwn(open, label) || typeof value !== 'boolean' || open[label] === value) return
      open[label] = value
      persist()
    },
  }
  states.set(key, state)
  persist()
  return state
}
