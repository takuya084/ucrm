import test from 'node:test'
import assert from 'node:assert/strict'
import { allergyNotice } from '../resources/js/lib/notice.mjs'

test('explicit empty placeholders are omitted only from display', () => {
  for (const value of [null, '', '　', 'なし', '【架空】特記事項なし', '【架空】 特記事項 なし。', 'アレルギーはありません']) assert.equal(allergyNotice(value), '')
})
test('real allergy details and qualified statements are retained verbatim', () => {
  for (const value of ['卵アレルギー', '【架空】卵の確認が必要', 'アレルギーなし。ただし卵は未確認', 'なし（保護者に再確認）', 'なしと聞いていたが発疹あり', '薬なし／食物アレルギーあり']) assert.equal(allergyNotice(value), value)
})
