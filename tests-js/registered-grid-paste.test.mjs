import {test} from 'node:test'
import assert from 'node:assert/strict'
import {pasteRegisteredRange} from '../public/transport-grid/grid.js'
const config={times:['14:30','17:30'],locations:{school:'学校',home:'自宅'},staff:[{id:1,name:'職員A'}]}
const rows=[{user_id:1,school_name:'第一小学校',pickup_time:'14:30',pickup_location:'home',pickup_staff_id:null},{user_id:2,school_name:'第二小学校',pickup_time:'14:30',pickup_location:'home',pickup_staff_id:null}]
test('registered grid values resolve per child and unsupported values reject the entire range',()=>{
 const before=JSON.stringify(rows)
 const next=pasteRegisteredRange(rows,0,0,[['14:30','学校','職員A'],['17:30','第二小学校','職員A']],config)
 assert.equal(next[0].pickup_location,'school');assert.equal(next[1].pickup_staff_id,1)
 for(const matrix of [[['14:30','学校'],['14:31','自宅']],[['14:30','未登録公園']],[['14:30','学校','他の職員']]]) assert.throws(()=>pasteRegisteredRange(rows,0,0,matrix,config))
 assert.equal(JSON.stringify(rows),before)
})
