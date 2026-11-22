import test from 'node:test'
import assert from 'node:assert/strict'
import { pasteSchoolDays } from '../resources/js/lib/schoolCalendar.mjs'

const days=[{date:'2026-07-01'},{date:'2026-07-02'}]
const row={id:1,services:Object.fromEntries(days.map(d=>[d.date,{type:'houday'}]))}
const initial={1:{'2026-07-01':'','2026-07-02':'school'}}
test('school calendar paste accepts only the named choices and keeps the original unchanged',()=>{
 const result=pasteSchoolDays([row],initial,days,0,0,'学校日\t休業日\r\n')
 assert.deepEqual(result[1],{'2026-07-01':'school','2026-07-02':'holiday'})
 assert.equal(initial[1]['2026-07-01'],'')
})
test('invalid cell or range cannot partially change the school calendar',()=>{
 assert.throws(()=>pasteSchoolDays([row],initial,days,0,0,'学校日\tinvalid'))
 assert.throws(()=>pasteSchoolDays([row],initial,days,0,1,'学校日\t休業日'))
 assert.deepEqual(initial,{1:{'2026-07-01':'','2026-07-02':'school'}})
})
test('school calendar paste cannot set an unverified or child-development day',()=>{
 for(const type of [null,'jidou'])assert.throws(()=>pasteSchoolDays([{...row,services:{'2026-07-01':{type}}}],initial,days,0,0,'学校日'))
})
