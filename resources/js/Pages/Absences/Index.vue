<script setup>
import {ref,onMounted,onBeforeUnmount,watch} from 'vue'
import {Head,router,usePage} from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import {mountAbsences,request} from '../../../../public/absence-board/board.js'
import '../../../../public/absence-board/board.css'
const props=defineProps({date:String})
const page=usePage()
const selectedDate=ref(props.date),root=ref(null)
let board,removeListener
const mountBoard=()=>{board?.destroy();board=mountAbsences(root.value,{date:props.date,load:()=>request(route('absences.data',{date:props.date})),save:p=>request(route('absences.data'),'PUT',p),settingsLabel:'事業所設定',settingsUrl:page.props.auth.staff_role==='admin'?route('facility.edit')+'#absence-calendar':null})}
onMounted(()=>{mountBoard();removeListener=router.on('before',event=>{if(board.hasChanges()&&!confirm('未保存の欠席記録があります。このページを離れますか？'))event.preventDefault()})})
watch(()=>props.date, date=>{selectedDate.value=date;mountBoard()})
onBeforeUnmount(()=>{board?.destroy();removeListener?.()})
</script>
<template><Head title="欠席連絡・相談記録"/><AuthenticatedLayout><template #header><h2 class="text-xl font-semibold text-gray-800">欠席連絡・相談記録</h2></template><div class="mx-auto max-w-7xl px-4 py-6"><label class="mb-4 block text-sm">対象日 <input type="date" v-model="selectedDate" @change="router.get(route('absences.index'),{date:selectedDate})" class="rounded-lg border-gray-300"/></label><div ref="root"/></div></AuthenticatedLayout></template>
