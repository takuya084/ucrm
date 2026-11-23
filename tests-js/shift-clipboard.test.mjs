import {test} from 'node:test'
import assert from 'node:assert/strict'
import {shiftTimes, planShiftPaste, copyShiftRange, shiftRectangle} from '../resources/js/lib/shiftClipboard.mjs'
const labels=[{name:'早番',is_off:false},{name:'遅番',is_off:false},{name:'休み',is_off:true}]
const original=[['早番','09:00','遅番','10:00'],['遅番','10:00','早番','09:00']]
const base={start:{row:0,col:0},rows:2,cols:4,read:(r,c)=>original[r][c],labels,times:shiftTimes()}
test('same time choices as the dropdown, including both boundaries',()=>{
 assert.equal(shiftTimes().length,68);assert.equal(shiftTimes()[0],'06:00');assert.equal(shiftTimes().at(-1),'22:45')
})
test('Excel rectangle spans days and multiple staff without losing trailing blank times',()=>{
 const plan=planShiftPaste({...base,text:'早番\t9:15\t休み\t\r\n遅番\t10:00\t早番\t08:30\r\n'})
 assert.equal(plan.length,8);assert.equal(plan[1].value,'09:15');assert.equal(plan[3].value,null)
})
test('unknown label rejects the entire block and leaves original data unchanged',()=>{
 const before=JSON.stringify(original)
 assert.throws(()=>planShiftPaste({...base,text:'遅番\t10:15\t未登録\t09:00'}),/未登録/)
 assert.equal(JSON.stringify(original),before)
})
test('unsupported times cannot be pasted, even when the other values are valid',()=>{
 for (const time of ['05:45','09:10','23:00','24:00','9:99','0.375','abc']) assert.throws(()=>planShiftPaste({...base,text:`早番\t${time}`}),/選択できない時刻/)
})
test('single time paste is normalized and empty value clears just that field',()=>{
 assert.equal(planShiftPaste({...base,start:{row:0,col:1},text:'6:00'})[0].value,'06:00')
 assert.deepEqual(planShiftPaste({...base,start:{row:0,col:1},text:''}),[{row:0,col:1,value:null}])
})
test('off label clears time; nonempty time cannot coexist with an off label',()=>{
 assert.deepEqual(planShiftPaste({...base,text:'休み'}),[{row:0,col:0,value:'休み'},{row:0,col:1,value:null}])
 assert.throws(()=>planShiftPaste({...base,text:'休み\t09:00'}),/休み系/)
 assert.throws(()=>planShiftPaste({...base,start:{row:0,col:1},read:(r,c)=>c===0?'休み':null,text:'09:00'}),/休み系/)
})
test('ragged and overflowing ranges are rejected, never truncated',()=>{
 for(const text of ['早番\t09:00\n早番','早番\n早番\n早番','早番\t09:00\t早番\t10:00\t早番']) assert.throws(()=>planShiftPaste({...base,text}))
})
test('reverse selection and TSV copying preserve column order and blank values',()=>{
 const rect=shiftRectangle({row:1,col:3},{row:0,col:0})
 assert.deepEqual(rect,{top:0,bottom:1,left:0,right:3})
 assert.equal(copyShiftRange(rect,(r,c)=>original[r][c]),'早番\t09:00\t遅番\t10:00\n遅番\t10:00\t早番\t09:00')
})
