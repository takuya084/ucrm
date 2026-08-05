<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  invoices:  Array,
  yearMonth: String,
  summary:   { type: Object, default: () => ({}) },
})

const selectedMonth = ref(props.yearMonth)
const fmt = (n) => Number(n).toLocaleString()

const STATUS_COLOR = {
  unpaid:  'bg-red-100 text-red-700',
  paid:    'bg-green-100 text-green-700',
  partial: 'bg-amber-100 text-amber-700',
  overdue: 'bg-red-200 text-red-800',
}
const STATUS_LABEL = { unpaid: '未入金', paid: '入金済', partial: '一部入金', overdue: '滞納' }
const METHOD_LABEL = { bank_transfer: '振込', cash: '現金', other: 'その他' }

const changeMonth = () => {
  router.get(route('billing.invoices.index'), { month: selectedMonth.value }, { preserveState: true, replace: true })
}

const generate = () => {
  if (!confirm(`${selectedMonth.value} の利用者請求書を生成しますか？`)) return
  router.post(route('billing.invoices.generate'), { year_month: selectedMonth.value })
}

// ── 入金クイック記録 ─────────────────────
const payingId = ref(null)
const payForm = reactive({
  paid_at: new Date().toISOString().slice(0, 10),
  payment_method: 'bank_transfer',
  paid_amount: 0,
})

const openPay = (inv) => {
  payingId.value = inv.id
  payForm.paid_at = new Date().toISOString().slice(0, 10)
  payForm.payment_method = inv.payment_method ?? 'bank_transfer'
  payForm.paid_amount = inv.total_amount
}

const savePay = (inv) => {
  router.patch(route('billing.invoices.update-payment', inv.id), {
    payment_status: payForm.paid_amount >= inv.total_amount ? 'paid' : 'partial',
    payment_method: payForm.payment_method,
    paid_amount:    payForm.paid_amount,
    paid_at:        payForm.paid_at,
  }, {
    preserveScroll: true,
    onSuccess: () => { payingId.value = null },
  })
}
</script>

<template>
  <Head title="利用者請求" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">利用者請求</h2>
        <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded-md hover:bg-gray-50">請求管理へ</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-white border border-gray-200 rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">年月</label>
            <input v-model="selectedMonth" type="month" @change="changeMonth" class="border border-gray-300 rounded-md px-3 py-1.5 text-sm" />
          </div>
          <button @click="generate" class="px-4 py-2 text-sm bg-primary-500 text-white rounded-md hover:bg-primary-600 transition">
            請求書一括生成
          </button>
        </div>

        <!-- 回収サマリ -->
        <div v-if="invoices.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
            <div class="text-xl font-bold text-gray-800">{{ fmt(summary.total ?? 0) }}円</div>
            <div class="text-xs text-gray-500 mt-1">請求合計（{{ summary.count ?? 0 }}件）</div>
          </div>
          <div class="bg-green-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-xl font-bold text-green-700">{{ fmt(summary.paid ?? 0) }}円</div>
            <div class="text-xs text-gray-500 mt-1">入金済</div>
          </div>
          <div :class="['rounded-lg shadow-sm p-4 text-center', (summary.outstanding ?? 0) > 0 ? 'bg-red-50' : 'bg-white']">
            <div :class="['text-xl font-bold', (summary.outstanding ?? 0) > 0 ? 'text-red-600' : 'text-gray-800']">
              {{ fmt(summary.outstanding ?? 0) }}円
            </div>
            <div class="text-xs text-gray-500 mt-1">未回収</div>
          </div>
          <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
            <div :class="['text-xl font-bold', (summary.unpaid_count ?? 0) > 0 ? 'text-amber-600' : 'text-gray-800']">
              {{ summary.unpaid_count ?? 0 }}件
            </div>
            <div class="text-xs text-gray-500 mt-1">未入金・一部入金</div>
          </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div v-if="invoices.length === 0" class="py-12 text-center text-gray-400 text-sm">
            請求書データがありません。対象月を選んで「請求書一括生成」を押すと、請求計算の結果から保護者宛の請求書が作成されます。
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">児童名</th>
                <th class="px-4 py-2 text-left">保護者</th>
                <th class="px-4 py-2 text-right">合計金額</th>
                <th class="px-4 py-2 text-left">入金状態</th>
                <th class="px-4 py-2 text-left">入金日／方法</th>
                <th class="px-4 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <template v-for="inv in invoices" :key="inv.id">
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium">{{ inv.child?.name }}</td>
                  <td class="px-4 py-3 text-xs">{{ inv.guardian?.name }}</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ fmt(inv.total_amount) }}円</td>
                  <td class="px-4 py-3">
                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[inv.payment_status]]">
                      {{ STATUS_LABEL[inv.payment_status] }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs text-gray-600">
                    <template v-if="inv.paid_at">
                      {{ inv.paid_at.slice(0, 10) }}
                      <span v-if="inv.payment_method" class="text-gray-400">／{{ METHOD_LABEL[inv.payment_method] }}</span>
                    </template>
                    <span v-else class="text-gray-300">―</span>
                  </td>
                  <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                    <button
                      v-if="!['paid'].includes(inv.payment_status)"
                      @click="payingId === inv.id ? payingId = null : openPay(inv)"
                      class="text-amber-600 hover:underline text-xs"
                    >{{ payingId === inv.id ? '閉じる' : '入金記録' }}</button>
                    <a
                      v-if="['paid','partial'].includes(inv.payment_status) && inv.paid_at"
                      :href="route('billing.invoices.receipt-pdf', inv.id)"
                      class="text-green-600 hover:underline text-xs"
                    >領収書</a>
                    <Link :href="route('billing.invoices.show', inv.id)" class="text-primary-600 hover:underline text-xs">詳細</Link>
                    <a :href="route('billing.invoices.pdf', inv.id)" class="text-green-600 hover:underline text-xs">請求書</a>
                  </td>
                </tr>
                <!-- 入金クイック記録フォーム -->
                <tr v-if="payingId === inv.id" class="bg-amber-50/60">
                  <td colspan="6" class="px-4 py-3">
                    <div class="flex flex-wrap items-end gap-3">
                      <div>
                        <label class="block text-[10px] text-gray-500 mb-1">入金日</label>
                        <input v-model="payForm.paid_at" type="date" class="border border-gray-300 rounded-md px-2 py-1 text-xs" />
                      </div>
                      <div>
                        <label class="block text-[10px] text-gray-500 mb-1">方法</label>
                        <select v-model="payForm.payment_method" class="border border-gray-300 rounded-md px-2 py-1 text-xs">
                          <option value="bank_transfer">振込</option>
                          <option value="cash">現金</option>
                          <option value="other">その他</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-[10px] text-gray-500 mb-1">入金額（全額 {{ fmt(inv.total_amount) }}円）</label>
                        <input v-model.number="payForm.paid_amount" type="number" min="0" class="border border-gray-300 rounded-md px-2 py-1 text-xs w-28 text-right" />
                      </div>
                      <button @click="savePay(inv)"
                        class="px-4 py-1.5 text-xs bg-amber-600 text-white rounded-md hover:bg-amber-700">
                        {{ payForm.paid_amount >= inv.total_amount ? '全額入金として記録' : '一部入金として記録' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
