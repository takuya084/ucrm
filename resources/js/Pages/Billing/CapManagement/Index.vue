<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  managements: Array,
  yearMonth:   String,
})

const selectedMonth = ref(props.yearMonth)
const fmt = (n) => Number(n).toLocaleString()

const RESULT_LABEL = { '1': '管理結果なし', '2': '管理結果あり', '3': '管理結果あり（按分）' }

const calculate = () => {
  if (!confirm(`${selectedMonth.value} の上限管理を計算しますか？`)) return
  Inertia.post(route('billing.cap-management.calculate'), { year_month: selectedMonth.value })
}

const changeMonth = () => {
  Inertia.get(route('billing.cap-management.index'), { month: selectedMonth.value }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="上限管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">上限管理</h2>
        <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">年月</label>
            <input v-model="selectedMonth" type="month" @change="changeMonth" class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
          </div>
          <button @click="calculate" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
            計算実行
          </button>
          <a :href="route('billing.cap-management.export', { year_month: selectedMonth })"
            class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600 transition">
            CSV出力
          </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="managements.length === 0" class="py-12 text-center text-gray-400 text-sm">
            上限管理データがありません
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">児童名</th>
                <th class="px-4 py-2 text-right">上限月額</th>
                <th class="px-4 py-2 text-right">全事業所合計</th>
                <th class="px-4 py-2 text-right">調整後</th>
                <th class="px-4 py-2 text-left">管理結果</th>
                <th class="px-4 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="mgmt in managements" :key="mgmt.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ mgmt.child?.name }}</td>
                <td class="px-4 py-3 text-right">{{ fmt(mgmt.cap_amount) }}円</td>
                <td class="px-4 py-3 text-right">{{ fmt(mgmt.total_copayment) }}円</td>
                <td class="px-4 py-3 text-right font-semibold">{{ fmt(mgmt.adjusted_copayment) }}円</td>
                <td class="px-4 py-3">
                  <span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ RESULT_LABEL[mgmt.management_result] }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <Link :href="route('billing.cap-management.show', mgmt.id)" class="text-indigo-600 hover:underline text-xs">詳細</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
