import { parseTSV } from '../../../public/transport-grid/grid.js'

export function pasteSchoolDays(rows, values, days, rowIndex, columnIndex, text) {
  const matrix=parseTSV(text)
  if (rowIndex+matrix.length>rows.length || matrix.some(row=>columnIndex+row.length>days.length)) throw new Error('貼り付け範囲が表を超えています。')
  const next=structuredClone(values)
  const choices={'学校日':'school','学':'school','1':'school','休業日':'holiday','休':'holiday','0':'holiday','未設定':'','':''}
  matrix.forEach((row,r)=>row.forEach((cell,c)=>{
    const child=rows[rowIndex+r], date=days[columnIndex+c].date, value=choices[cell.trim()]
    if (value===undefined) throw new Error('学校日・休業日・未設定のいずれかを貼り付けてください。')
    if (child.services[date].type!=='houday') throw new Error('受給者証で放デイを確認できる日だけ貼り付けできます。')
    next[child.id][date]=value
  }))
  return next
}
