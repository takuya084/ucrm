import {test} from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import vm from 'node:vm'
const context={};vm.createContext(context)
vm.runInContext(fs.readFileSync(new URL('../public/input-clipboard.js',import.meta.url),'utf8'),context)
const api=context.RegisteredChoiceClipboard
const option=(label,value,extra={})=>({label,value,selected:false,...extra})
const plain=value=>JSON.parse(JSON.stringify(value))
test('copy uses visible labels and disambiguates duplicate candidates',()=>{
 const a=option('同名','1',{selected:true}),b=option('同名','2')
 assert.equal(api.copySelection({options:[a,b]}),'同名（#1）')
 assert.equal(api.selectPlan({options:[a,b]},'同名（#1）')[0],a)
 assert.throws(()=>api.selectPlan({options:[a,b]},'同名'),/複数/)
})
test('only registered, enabled candidates can be pasted; invalid input makes no mutation',()=>{
 const a=option('電話','tel',{selected:true}),b=option('メール','email'),c=option('禁止','blocked',{disabled:true})
 const el={options:[a,b,c]}
 assert.equal(api.selectPlan(el,'メール')[0],b)
 for(const value of ['不明','禁止','電話\tメール','電話\nメール']) assert.throws(()=>api.selectPlan(el,value))
 assert(a.selected);assert(!b.selected)
})
test('time normalization never rounds unsupported minutes or expands the actual choices',()=>{
 const a=option('09:00','09:00'),el={options:[a,option('09:15','09:15')]}
 assert.equal(api.selectPlan(el,'９：００')[0],a)
 for(const value of ['09:10','05:45','24:00']) assert.throws(()=>api.selectPlan(el,value))
})
test('multiple selections validate every row before returning the complete plan',()=>{
 const a=option('学校A','1'),b=option('学校B','2'),el={multiple:true,options:[a,b]}
 assert.deepEqual(plain(api.selectPlan(el,'学校A\r\n学校B\r\n').map(o=>o.value)),['1','2'])
 assert.throws(()=>api.selectPlan(el,'学校A\n未登録学校'))
 assert.equal(api.selectPlan(el,'').length,0)
})
test('quoted Excel labels round-trip including literal double quotes',()=>{
 const o=option('早番 "A"','1',{selected:true}),el={options:[o]}
 assert.equal(api.selectPlan(el,api.copySelection(el))[0],o)
 assert.throws(()=>api.parse('"未完了'))
})
test('display labels take precedence over internal values, with no arbitrary ID coercion',()=>{
 const a=option('1','A'),b=option('別候補','1'),el={options:[a,b]}
 assert.equal(api.selectPlan(el,'1')[0],a)
 assert.throws(()=>api.selectPlan(el,'A'))
})
