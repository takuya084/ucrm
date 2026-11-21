<script setup>
import {ref,onMounted,onBeforeUnmount} from 'vue'
import {router} from '@inertiajs/vue3'
import {mountAbsenceCalendar,request} from '../../../public/absence-board/board.js'
import '../../../public/absence-board/board.css'
const root=ref(null)
let calendar,removeListener
onMounted(()=>{
  calendar=mountAbsenceCalendar(root.value,{load:()=>request(route('absences.calendar')),save:p=>request(route('absences.calendar'),'PUT',p)})
  removeListener=router.on('before',event=>{if(calendar.hasChanges()&&!confirm('未保存の営業日設定があります。このページを離れますか？'))event.preventDefault()})
})
onBeforeUnmount(()=>{calendar?.destroy();removeListener?.()})
</script>
<template><div id="absence-calendar" ref="root" class="scroll-mt-4"/></template>
