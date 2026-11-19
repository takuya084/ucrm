import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

// Runs the exact common module in Chromium; no simulated DOM and no external API.
export async function checkPicker(page, expect) {
  const source = await readFile(fileURLToPath(new URL('../public/transport-grid/grid.js', import.meta.url)), 'utf8')
  await page.evaluate(async source => {
    const { mountTransportGrid } = await import('data:text/javascript;base64,' + btoa(unescape(encodeURIComponent(source))))
    const root = document.createElement('div'); root.id = 'picker-regression'; (document.querySelector('main') ?? document.body).append(root)
    const row = { user_id: 1, name: '候補確認児童', exists: true, snapshot: 'test', pickup_time: '14:30', pickup_location: 'school', pickup_staff_id: 7, dropoff_time: '17:30', dropoff_location: 'home', dropoff_staff_id: null }
    const config = { rows: [row], times: ['14:30', '15:00', '17:30'], locations: { school: '学校', home: '自宅' }, staff: [{ id: 7, name: '候補職員A' }, { id: 8, name: '候補職員B' }] }
    window.pickerSaves = []; window.disposePicker = mountTransportGrid(root, { date: '2026-12-01', load: async () => config, save: async payload => { window.pickerSaves.push(payload); Object.assign(row, payload.rows[0]) } })
  }, source.slice(0, source.indexOf("if (typeof document !== 'undefined')")))
  const root = page.locator('#picker-regression')
  const input = root.getByRole('combobox', { name: '候補確認児童 迎え担当', exact: true })
  const toggle = root.getByRole('button', { name: '候補確認児童 迎え担当の候補をすべて表示', exact: true })
  await expect(input).toHaveValue('候補職員A')
  await toggle.click()
  await expect(page.getByRole('option', { name: '候補職員A', exact: true })).toBeVisible()
  await expect(page.getByRole('option', { name: '候補職員B', exact: true })).toBeVisible()
  await expect(input).toHaveValue('候補職員A') // Opening must not dirty or clear the value.
  await expect(root.getByRole('button', { name: '変更をまとめて保存', exact: true })).toBeDisabled()
  const optionsId = await input.getAttribute('aria-controls')
  const options = page.locator('#' + optionsId)
  const box = await options.boundingBox()
  expect(box.x).toBeGreaterThanOrEqual(0); expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize().width)
  await page.getByRole('option', { name: '候補職員A', exact: true }).click()
  await expect(root.getByRole('button', { name: '変更をまとめて保存', exact: true })).toBeDisabled()
  await toggle.click()
  const staffB = page.getByRole('option', { name: '候補職員B', exact: true })
  if (page.viewportSize().width < 600) await staffB.tap(); else await staffB.click()
  await expect(input).toHaveAttribute('aria-expanded', 'false')
  await expect(input).toHaveValue('候補職員B')
  await input.fill('候補職員A')
  await expect(page.getByRole('option', { name: '候補職員B', exact: true })).toHaveCount(0)
  await input.press('Escape'); await toggle.click()
  await expect(page.getByRole('option', { name: '候補職員B', exact: true })).toBeVisible()
  await input.press('Escape'); await input.press('ArrowDown'); await input.press('ArrowDown'); await input.press('Enter')
  await expect(input).toHaveValue('候補職員B')
  await input.press('ArrowUp'); await input.press('Enter')
  await expect(input).toHaveValue('候補職員B')
  await root.getByRole('button', { name: '変更をまとめて保存', exact: true }).click()
  await expect(root.getByText('変更をまとめて保存しました。', { exact: true })).toBeVisible()
  expect(await page.evaluate(() => window.pickerSaves[0].rows[0].pickup_staff_id)).toBe(8)
  await page.evaluate(() => window.disposePicker())
  await expect(options).toHaveCount(0)
}
