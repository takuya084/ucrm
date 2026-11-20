<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import SharedTransportGrid from '@/Components/SharedTransportGrid.vue'
import DailyTransportBoard from '@/Components/DailyTransportBoard.vue'
const props = defineProps({ date: String, canManage: Boolean, transportAssignments: Array,
  transportStaff: Array, transportVisits: Array, transportState: String, localTransportCount: Number })
const selectedDate = ref(props.date)
watch(() => props.date, date => { selectedDate.value = date })
const changeDate = () => { if (selectedDate.value) router.get(route('transport.index'), { date: selectedDate.value }) }
</script>

<template>
  <Head title="送迎一覧" />
  <AuthenticatedLayout>
    <template #header><h2 class="text-xl font-semibold text-gray-800">送迎一覧</h2></template>
    <div class="py-8"><div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
      <label class="block text-sm font-medium text-gray-700">対象日
        <input v-model="selectedDate" type="date" @change="changeDate" class="mt-1 block max-w-full rounded-md border-gray-300" />
      </label>
      <p class="text-sm text-gray-600">スタッフ全員の時刻・場所・担当を確認します。{{ canManage ? '変更はこの画面でまとめて保存します。' : '変更は管理者・児発管に依頼してください。' }}</p>
      <SharedTransportGrid v-if="transportState !== 'local'" :key="date" :date="date" :can-manage="canManage" />
      <DailyTransportBoard v-else :key="date" :date="date" :visits="transportVisits"
        :can-manage="canManage" :assignments="transportAssignments" :staff="transportStaff"
        :sync-state="transportState" :local-count="localTransportCount" />
    </div></div>
  </AuthenticatedLayout>
</template>
