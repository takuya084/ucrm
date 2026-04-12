<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'

const props = defineProps({ detail: Object })
const fmt = (n) => Number(n).toLocaleString()
</script>

<template>
  <Head :title="`明細詳細 - ${detail.child?.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ detail.child?.name }} - 請求明細</h2>
        <Link :href="route('billing.show', detail.billing_period_id)" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">戻る</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <!-- 概要 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><span class="text-xs text-gray-500 block">種別</span>{{ detail.service_type === 'houday' ? '放課後等デイサービス' : '児童発達支援' }}</div>
            <div><span class="text-xs text-gray-500 block">利用日数</span>{{ detail.total_days }}日</div>
            <div><span class="text-xs text-gray-500 block">合計単位数</span>{{ fmt(detail.total_units) }}</div>
            <div><span class="text-xs text-gray-500 block">単位単価</span>{{ detail.unit_price_yen }}円</div>
            <div><span class="text-xs text-gray-500 block">費用合計</span><span class="font-bold">{{ fmt(detail.total_amount) }}円</span></div>
            <div><span class="text-xs text-gray-500 block">給付費</span>{{ fmt(detail.insurance_amount) }}円</div>
            <div><span class="text-xs text-gray-500 block">利用者負担(計算値)</span>{{ fmt(detail.copayment_amount) }}円</div>
            <div><span class="text-xs text-gray-500 block">利用者負担(上限適用後)</span><span class="font-bold text-red-600">{{ fmt(detail.copayment_cap_applied) }}円</span></div>
          </div>
        </div>

        <!-- サービスコード別内訳 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">サービスコード別内訳</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">コード</th>
                <th class="px-4 py-2 text-left">サービス名</th>
                <th class="px-4 py-2 text-right">回数</th>
                <th class="px-4 py-2 text-right">単位/回</th>
                <th class="px-4 py-2 text-right">合計単位</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="line in detail.billing_detail_lines" :key="line.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs">{{ line.service_code }}</td>
                <td class="px-4 py-2">{{ line.service_name }}</td>
                <td class="px-4 py-2 text-right">{{ line.count }}</td>
                <td class="px-4 py-2 text-right">{{ fmt(line.units_per_count) }}</td>
                <td class="px-4 py-2 text-right font-medium">{{ fmt(line.total_units) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 日別サービス実績 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">日別サービス実績</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">日付</th>
                <th class="px-4 py-2 text-left">コード</th>
                <th class="px-4 py-2 text-right">単位数</th>
                <th class="px-4 py-2 text-center">送迎(迎)</th>
                <th class="px-4 py-2 text-center">送迎(送)</th>
                <th class="px-4 py-2 text-center">延長</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="dsr in detail.daily_service_records" :key="dsr.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 text-xs">{{ dsr.usage_record?.date?.slice(0, 10) }}</td>
                <td class="px-4 py-2 font-mono text-xs">{{ dsr.service_code }}</td>
                <td class="px-4 py-2 text-right">{{ dsr.units }}</td>
                <td class="px-4 py-2 text-center">{{ dsr.is_pickup ? '○' : '' }}</td>
                <td class="px-4 py-2 text-center">{{ dsr.is_dropoff ? '○' : '' }}</td>
                <td class="px-4 py-2 text-center">{{ dsr.is_extension ? '○' : '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
