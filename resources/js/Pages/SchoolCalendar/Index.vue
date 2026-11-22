<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Layout from '@/Layouts/Authenticated.vue'
import DisclosurePanel from '@/Components/DisclosurePanel.vue'
import MenuDestination from '@/Components/MenuDestination.vue'
import { pasteSchoolDays } from '@/lib/schoolCalendar.mjs'

const props=defineProps({month:String,days:Array,rows:Array,revision:Number,locked:Boolean})
const values=ref({}), baseline=ref({}), revision=ref(props.revision), targetMonth=ref(props.month)
const selected=ref([]), school=ref(''), busy=ref(false), message=ref(''), error=ref('')
const start=ref(props.days[0].date), end=ref(props.days.at(-1).date), weekdays=ref([1,2,3,4,5]), bulkValue=ref('school')
let discarding=false, removeNavigation
const reset=()=>{
  values.value=Object.fromEntries(props.rows.map(row=>[row.id,Object.fromEntries(props.days.map(day=>[day.date,row.values[day.date]===null?'':row.values[day.date]?'school':'holiday']))]))
  baseline.value=JSON.parse(JSON.stringify(values.value));revision.value=props.revision;targetMonth.value=props.month
  selected.value=[];start.value=props.days[0].date;end.value=props.days.at(-1).date
}
watch(()=>[props.rows,props.month],reset,{immediate:true})
const changes=computed(()=>props.rows.flatMap(row=>props.days.filter(day=>values.value[row.id][day.date]!==baseline.value[row.id][day.date]).map(day=>({child_id:row.id,date:day.date,value:values.value[row.id][day.date]===''?null:values.value[row.id][day.date]==='school'}))))
const dirty=computed(()=>changes.value.length>0)
const schools=computed(()=>[...new Set(props.rows.map(row=>row.school||'学校未登録'))])
const visible=computed(()=>props.rows.filter(row=>!school.value||(row.school||'学校未登録')===school.value))
const missing=row=>props.days.filter(day=>row.services[day.date].type==='houday'&&values.value[row.id][day.date]==='').length
const unknown=row=>props.days.some(day=>!row.services[day.date].type)
const missingChildren=computed(()=>props.rows.filter(row=>missing(row)>0).length)
const unknownChildren=computed(()=>props.rows.filter(unknown).length)
watch(school,()=>{selected.value=[]})
const toggleAll=event=>{selected.value=event.target.checked?visible.value.map(row=>row.id):[]}
const applyBulk=()=>{
  error.value='';message.value=''
  if (!selected.value.length) {error.value='設定する児童を選択してください。';return}
  if (start.value>end.value || start.value.slice(0,7)!==props.month || end.value.slice(0,7)!==props.month) {error.value='対象月内の期間を選択してください。';return}
  let count=0
  props.rows.filter(row=>selected.value.includes(row.id)).forEach(row=>props.days.filter(day=>day.date>=start.value&&day.date<=end.value&&weekdays.value.includes(day.weekday)&&row.services[day.date].type==='houday').forEach(day=>{values.value[row.id][day.date]=bulkValue.value;count++}))
  message.value=count?`${count}枠に適用しました。保存してください。`:'対象の曜日・放デイの登録がある日がありません。'
}
const copyCell=(event,id,date)=>{event.preventDefault();event.clipboardData.setData('text/plain',({school:'学校日',holiday:'休業日','':'未設定'})[values.value[id][date]])}
const paste=(event,rowIndex,columnIndex)=>{
  event.preventDefault();if(props.locked||busy.value)return
  try {values.value=pasteSchoolDays(visible.value,JSON.parse(JSON.stringify(values.value)),props.days,rowIndex,columnIndex,event.clipboardData.getData('text/plain'));error.value='';message.value='貼り付けました。保存してください。'}
  catch(e){error.value=e.message}
}
const save=async()=>{
  if(busy.value||!dirty.value||props.locked)return
  busy.value=true;error.value='';message.value=''
  try {
    const response=await axios.put(route('school-calendar.update'),{month:props.month,revision:revision.value,cells:changes.value})
    revision.value=response.data.revision;baseline.value=JSON.parse(JSON.stringify(values.value));message.value=response.data.message
  } catch(e) {
    error.value=e.response?.status===409?'他の職員が更新しました。入力内容は残しています。内容を控えてから最新の状態を確認してください。':e.response?.status===423?'請求確定済みのため変更できません。':Object.values(e.response?.data?.errors||{}).flat()[0]||'保存できませんでした。入力内容を残しています。最新の状態を確認してください。'
  } finally {busy.value=false}
}
const navigate=(refresh=false)=>{
  if(!refresh&&!targetMonth.value){targetMonth.value=props.month;return}
  if(busy.value)return
  if(dirty.value&&!confirm('未保存の変更があります。変更を破棄して最新の状態を表示しますか？')){targetMonth.value=props.month;return}
  discarding=true
  const options={onFinish:()=>{discarding=false},onSuccess:()=>{reset();error.value='';message.value=''}}
  if(refresh)router.reload(options)
  else router.get(route('school-calendar.index'),{month:targetMonth.value},options)
}
const beforeUnload=event=>{if(dirty.value||busy.value){event.preventDefault();event.returnValue=''}}
onMounted(()=>{window.addEventListener('beforeunload',beforeUnload);removeNavigation=router.on('before',event=>{if(busy.value){event.preventDefault();return}if(!discarding&&dirty.value&&!confirm('未保存の変更を破棄して移動しますか？'))event.preventDefault()})})
onUnmounted(()=>{window.removeEventListener('beforeunload',beforeUnload);removeNavigation?.()})
</script>

<template>
  <Head title="学校日・休業日設定" />
  <Layout>
    <template #header><h2 class="text-xl font-semibold text-gray-800">学校日・休業日設定</h2></template>
    <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <label class="text-sm font-medium text-gray-700">対象月<input type="month" v-model="targetMonth" :disabled="busy" @change="navigate()" class="mt-1 block rounded-md border-gray-300" /></label>
        <div class="flex flex-wrap gap-4 text-sm"><span>未設定 <strong class="text-amber-800">{{ missingChildren }}人</strong></span><span>受給者証の確認 <strong class="text-amber-800">{{ unknownChildren }}人</strong></span><span>{{ dirty ? `未保存 ${changes.length}枠` : '保存済み' }}</span></div>
        <button type="button" class="text-sm text-primary-700 underline disabled:opacity-50" :disabled="busy" @click="navigate(true)">最新の状態に更新</button>
      </div>
      <div class="rounded-xl border bg-white p-4 text-sm text-gray-700">
        サービス種別は、その日に有効な受給者証の登録を参照します。児童発達支援の学校日設定は対象外です。
        <div class="mt-2"><MenuDestination prefix="受給者証の設定先：メニュー" label="児童の記録" :href="route('children.index')" /></div>
      </div>
      <p v-if="locked" role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">請求確定済みの月です。設定は変更できません。</p>
      <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ error }}</p>
      <p v-if="message" role="status" class="text-sm text-primary-800">{{ message }}</p>
      <DisclosurePanel id="school-calendar-bulk" title="一括設定・コピペの使い方">
        <fieldset :disabled="locked || busy" class="space-y-4 text-sm">
          <div class="flex flex-wrap gap-3"><label>開始日<input type="date" v-model="start" :min="days[0].date" :max="days.at(-1).date" class="ml-2 rounded-md border-gray-300" /></label><label>終了日<input type="date" v-model="end" :min="days[0].date" :max="days.at(-1).date" class="ml-2 rounded-md border-gray-300" /></label></div>
          <div class="flex flex-wrap gap-3"><button type="button" @click="weekdays=[1,2,3,4,5]" class="text-primary-700 underline">平日を選択</button><button type="button" @click="weekdays=[0,6]" class="text-primary-700 underline">土日を選択</button><button type="button" @click="weekdays=[0,1,2,3,4,5,6]" class="text-primary-700 underline">すべての曜日</button><span class="text-gray-600">児童 {{ selected.length }}人を選択中</span></div>
          <div class="flex flex-wrap gap-4"><label v-for="(label,index) in ['日','月','火','水','木','金','土']" :key="label"><input type="checkbox" :value="index" v-model="weekdays" /> {{ label }}</label></div>
          <div class="flex flex-wrap items-center gap-3"><label>設定値<select v-model="bulkValue" class="ml-2 rounded-md border-gray-300"><option value="school">学校日</option><option value="holiday">休業日</option><option value="">未設定に戻す</option></select></label><button type="button" @click="applyBulk" class="rounded-md border border-primary-300 px-4 py-2 font-semibold text-primary-700">選択した児童に適用</button></div>
          <p class="text-gray-600">学校の予定を確認して設定してください。祝日・振替休日も個別に確認します。Excelから「学校日」「休業日」「未設定」を複数セルまとめて貼り付けできます。</p>
        </fieldset>
      </DisclosurePanel>
      <label class="block text-sm text-gray-700">学校で絞り込み<select v-model="school" :disabled="busy" class="ml-2 max-w-full rounded-md border-gray-300"><option value="">すべて</option><option v-for="name in schools" :key="name">{{ name }}</option></select></label>
      <div class="overflow-x-auto rounded-xl border bg-white" tabindex="0" aria-label="児童別の月次学校日設定表">
        <table class="min-w-max text-sm"><thead class="bg-gray-50"><tr><th class="sticky left-0 z-20 bg-gray-50 px-3 py-3"><input type="checkbox" aria-label="表示中の児童を全選択" :disabled="locked||busy" @change="toggleAll" /></th><th class="sticky left-12 z-20 min-w-[220px] bg-gray-50 px-3 text-left">児童・学校・サービス種別</th><th v-for="day in days" :key="day.date" class="min-w-[100px] px-2 py-3" :class="day.weekday===0?'text-red-600':day.weekday===6?'text-blue-600':''">{{ day.day }}日 <span class="text-xs">{{ ['日','月','火','水','木','金','土'][day.weekday] }}</span></th></tr></thead>
          <tbody><tr v-for="(row,rowIndex) in visible" :key="row.id" class="border-t">
            <td class="sticky left-0 z-10 bg-white px-3"><input type="checkbox" :value="row.id" v-model="selected" :aria-label="row.name+'を選択'" :disabled="locked||busy" /></td>
            <th class="sticky left-12 z-10 bg-white px-3 py-3 text-left"><p class="font-semibold">{{ row.name }}</p><p class="text-xs font-normal text-gray-600">{{ row.school || '学校未登録' }}</p><p class="mt-1 text-xs font-normal">{{ [...new Set(days.map(day=>row.services[day.date].label))].join('／') }}</p><p v-if="missing(row)" class="text-xs font-normal text-amber-800">未設定 {{ missing(row) }}日</p><p v-if="unknown(row)" class="text-xs font-normal text-red-700">受給者証を確認</p></th>
            <td v-for="(day,columnIndex) in days" :key="day.date" class="px-1 py-2" :class="values[row.id][day.date]!==baseline[row.id][day.date]?'bg-yellow-50':''">
              <span v-if="row.services[day.date].type==='jidou'" class="block text-center text-xs text-gray-400">対象外</span>
              <span v-else-if="!row.services[day.date].type" class="block max-w-[96px] text-xs text-red-700" :title="row.services[day.date].reason">受給者証<br>未確認</span>
              <select v-else v-model="values[row.id][day.date]" :aria-label="row.name+' '+day.date" :disabled="locked||busy" @paste="paste($event,rowIndex,columnIndex)" @copy="copyCell($event,row.id,day.date)" class="w-[96px] rounded-md border-gray-300 py-2 pl-2 pr-6 text-sm" :class="values[row.id][day.date]===''?'text-amber-800':values[row.id][day.date]==='holiday'?'text-blue-800':'text-gray-800'"><option value="">未設定</option><option value="school">学校日</option><option value="holiday">休業日</option></select>
            </td>
          </tr></tbody>
        </table>
        <p v-if="!visible.length" class="p-5 text-sm text-gray-600">対象の児童はいません。</p>
      </div>
      <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-4 shadow-sm"><p class="text-sm text-gray-600">未設定の放デイ利用日は、請求計算・確定時に確認が必要です。</p><button type="button" @click="save" :disabled="!dirty||locked||busy" class="rounded-lg bg-primary-600 px-5 py-3 font-semibold text-white disabled:opacity-40">{{ busy?'保存中…':'変更をまとめて保存' }}</button></div>
    </div>
  </Layout>
</template>
