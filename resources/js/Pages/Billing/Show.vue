<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  period: Object,
})

const STATUS_LABEL = {
  draft: '下書き', calculating: '計算中', confirmed: '確定済',
  submitted: '提出済', completed: '完了', error: 'エラー',
}

const confirmPeriod = () => {
  if (!confirm('この請求を確定しますか？確定後は自動再計算できなくなります。')) return
  Inertia.patch(route('billing.confirm', props.period.id))
}

const fmt = (n) => Number(n).toLocaleString()
</script>

<template>
  <Head :title="`請求詳細 ${period.year_month}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ period.year_month }} 請求詳細</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">
            戻る
          </Link>
          <button v-if="period.status === 'draft'" @click="confirmPeriod"
            class="px-4 py-1.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            確定する
          </button>
          <a v-if="['confirmed','submitted','completed'].includes(period.status)"
            :href="route('billing.export', period.id)"
            class="px-4 py-1.5 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition">
            請求CSV出力
          </a>
          <a v-if="['confirmed','submitted','completed'].includes(period.status)"
            :href="route('billing.export-performance', period.id)"
            class="px-4 py-1.5 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition">
            実績CSV出力
          </a>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- サマリ -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div>
              <div class="text-xs text-gray-500">ステータス</div>
              <div class="text-lg font-bold">{{ STATUS_LABEL[period.status] }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">対象児童数</div>
              <div class="text-lg font-bold">{{ period.billing_details?.length ?? 0 }}名</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">費用合計</div>
              <div class="text-lg font-bold">{{ fmt(period.billing_details?.reduce((s, d) => s + d.total_amount, 0) ?? 0) }}円</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">利用者負担合計</div>
              <div class="text-lg font-bold">{{ fmt(period.billing_details?.reduce((s, d) => s + d.copayment_cap_applied, 0) ?? 0) }}円</div>
            </div>
          </div>
        </div>

        <!-- 児童別明細テーブル -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">児童別請求明細</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                  <th class="px-4 py-2 text-left">児童名</th>
                  <th class="px-4 py-2 text-left">種別</th>
                  <th class="px-4 py-2 text-right">利用日数</th>
                  <th class="px-4 py-2 text-right">合計単位</th>
                  <th class="px-4 py-2 text-right">費用合計</th>
                  <th class="px-4 py-2 text-right">給付費</th>
                  <th class="px-4 py-2 text-right">利用者負担</th>
                  <th class="px-4 py-2 text-right">操作</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="detail in period.billing_details" :key="detail.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium">{{ detail.child?.name }}</td>
                  <td class="px-4 py-3 text-xs">
                    <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                      {{ detail.service_type === 'houday' ? '放デイ' : '児発' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">{{ detail.total_days }}日</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.total_units) }}</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.total_amount) }}円</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.insurance_amount) }}円</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ fmt(detail.copayment_cap_applied) }}円</td>
                  <td class="px-4 py-3 text-right space-x-2">
                    <Link :href="route('billing.details.show', detail.id)" class="text-indigo-600 hover:underline text-xs">詳細</Link>
                    <Link v-if="period.status === 'draft'" :href="route('billing.details.edit', detail.id)" class="text-gray-500 hover:underline text-xs">編集</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
