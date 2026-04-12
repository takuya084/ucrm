<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  invoices:  Array,
  yearMonth: String,
})

const selectedMonth = ref(props.yearMonth)
const fmt = (n) => Number(n).toLocaleString()

const STATUS_COLOR = {
  unpaid:  'bg-red-100 text-red-700',
  paid:    'bg-green-100 text-green-700',
  partial: 'bg-yellow-100 text-yellow-700',
  overdue: 'bg-red-200 text-red-800',
}
const STATUS_LABEL = { unpaid: '未入金', paid: '入金済', partial: '一部入金', overdue: '滞納' }

const changeMonth = () => {
  Inertia.get(route('billing.invoices.index'), { month: selectedMonth.value }, { preserveState: true, replace: true })
}

const generate = () => {
  if (!confirm(`${selectedMonth.value} の利用者請求書を生成しますか？`)) return
  Inertia.post(route('billing.invoices.generate'), { year_month: selectedMonth.value })
}
</script>

<template>
  <Head title="利用者請求" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">利用者請求</h2>
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
          <button @click="generate" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
            請求書一括生成
          </button>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="invoices.length === 0" class="py-12 text-center text-gray-400 text-sm">
            請求書データがありません
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">児童名</th>
                <th class="px-4 py-2 text-left">保護者</th>
                <th class="px-4 py-2 text-right">自己負担額</th>
                <th class="px-4 py-2 text-right">合計金額</th>
                <th class="px-4 py-2 text-left">入金状態</th>
                <th class="px-4 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ inv.child?.name }}</td>
                <td class="px-4 py-3 text-xs">{{ inv.guardian?.name }}</td>
                <td class="px-4 py-3 text-right">{{ fmt(inv.copayment_amount) }}円</td>
                <td class="px-4 py-3 text-right font-semibold">{{ fmt(inv.total_amount) }}円</td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[inv.payment_status]]">
                    {{ STATUS_LABEL[inv.payment_status] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                  <Link :href="route('billing.invoices.show', inv.id)" class="text-indigo-600 hover:underline text-xs">詳細</Link>
                  <a :href="route('billing.invoices.pdf', inv.id)" class="text-green-600 hover:underline text-xs">PDF</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
