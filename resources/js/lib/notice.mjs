// Suppress only explicit empty placeholders. Never alter the saved note or
// discard meaningful text merely because it includes a word such as "なし".
export function allergyNotice(value) {
  const text = String(value ?? '').trim()
  const normalized = text.replace(/^(?:【架空】\s*)+/u, '').replace(/[\s。．.!！]/gu, '')
  const empty = /^(?:なし|無し|特になし|特記事項なし|特記事項無し|特記なし|アレルギーなし|アレルギー無し|アレルギーはありません|特記事項はありません)$/u
  return !normalized || empty.test(normalized) ? '' : text
}
