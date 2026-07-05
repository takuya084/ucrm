<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({ invoice: Object })
const fmt = (n) => Number(n).toLocaleString()

const STATUS_LABEL = { unpaid: '未入金', paid: '入金済', partial: '一部入金', overdue: '滞納' }
const METHOD_LABEL = { bank_transfer: '振込', cash: '現金', other: 'その他' }

const paymentForm = ref({
  payment_status: props.invoice.payment_status,
  payment_method: props.invoice.payment_method ?? '',
  paid_amount: props.invoice.paid_amount ?? props.invoice.total_amount,
  paid_at: props.invoice.paid_at?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
  notes: props.invoice.notes ?? '',
})

const updatePayment = () => {
  Inertia.patch(route('billing.invoices.update-payment', props.invoice.id), paymentForm.value)
}
</script>

<template>
  <Head :title="`請求書 - ${invoice.child?.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ invoice.child?.name }} - 請求書詳細</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.invoices.index', { month: invoice.year_month })" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">戻る</Link>
          <a v-if="['paid','partial'].includes(invoice.payment_status) && invoice.paid_at"
            :href="route('billing.invoices.receipt-pdf', invoice.id)"
            class="px-4 py-1.5 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700 transition">領収書PDF</a>
          <a :href="route('billing.invoices.pdf', invoice.id)" class="px-4 py-1.5 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition">請求書PDF</a>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 請求書情報 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div><span class="text-xs text-gray-500 block">年月</span>{{ invoice.year_month }}</div>
            <div><span class="text-xs text-gray-500 block">児童名</span>{{ invoice.child?.name }}</div>
            <div><span class="text-xs text-gray-500 block">保護者</span>{{ invoice.guardian?.name }}</div>
            <div><span class="text-xs text-gray-500 block">自己負担額</span>{{ fmt(invoice.copayment_amount) }}円</div>
            <div><span class="text-xs text-gray-500 block">その他費用</span>{{ fmt(invoice.other_charges) }}円</div>
            <div><span class="text-xs text-gray-500 block">請求合計</span><span class="font-bold text-lg">{{ fmt(invoice.total_amount) }}円</span></div>
          </div>
        </div>

        <!-- サービスコード内訳 -->
        <div v-if="invoice.billing_detail?.billing_detail_lines" class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">サービス内訳</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">サービス名</th>
                <th class="px-4 py-2 text-right">回数</th>
                <th class="px-4 py-2 text-right">合計単位</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="line in invoice.billing_detail.billing_detail_lines" :key="line.id">
                <td class="px-4 py-2">{{ line.service_name }}</td>
                <td class="px-4 py-2 text-right">{{ line.count }}</td>
                <td class="px-4 py-2 text-right">{{ fmt(line.total_units) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 入金管理 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">入金管理</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">入金状態</label>
              <select v-model="paymentForm.payment_status" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                <option v-for="(label, val) in STATUS_LABEL" :key="val" :value="val">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">入金方法</label>
              <select v-model="paymentForm.payment_method" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                <option value="">未選択</option>
                <option v-for="(label, val) in METHOD_LABEL" :key="val" :value="val">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">入金額</label>
              <input v-model.number="paymentForm.paid_amount" type="number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">入金日</label>
              <input v-model="paymentForm.paid_at" type="date" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs text-gray-500 mb-1">備考</label>
              <textarea v-model="paymentForm.notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"></textarea>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <button @click="updatePayment" class="px-6 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
              更新
            </button>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
