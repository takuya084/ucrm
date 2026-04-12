<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'

const props = defineProps({ management: Object })
const fmt = (n) => Number(n).toLocaleString()
const RESULT_LABEL = { '1': '管理結果なし', '2': '管理結果あり', '3': '管理結果あり（按分）' }
</script>

<template>
  <Head :title="`上限管理 - ${management.child?.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ management.child?.name }} - 上限管理詳細</h2>
        <Link :href="route('billing.cap-management.index', { month: management.year_month })" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">戻る</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <!-- 概要 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><span class="text-xs text-gray-500 block">対象年月</span>{{ management.year_month }}</div>
            <div><span class="text-xs text-gray-500 block">上限月額</span>{{ fmt(management.cap_amount) }}円</div>
            <div><span class="text-xs text-gray-500 block">全事業所合計</span>{{ fmt(management.total_copayment) }}円</div>
            <div><span class="text-xs text-gray-500 block">管理結果</span>{{ RESULT_LABEL[management.management_result] }}</div>
          </div>
        </div>

        <!-- 事業所別内訳 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">事業所別内訳</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">事業所名</th>
                <th class="px-4 py-2 text-right">総費用額</th>
                <th class="px-4 py-2 text-right">利用者負担額</th>
                <th class="px-4 py-2 text-right">調整後負担額</th>
                <th class="px-4 py-2 text-center">管理事業所</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="d in management.details" :key="d.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  {{ d.facility_name }}
                  <span v-if="d.is_managing_facility" class="ml-1 text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">管理</span>
                </td>
                <td class="px-4 py-3 text-right">{{ fmt(d.total_amount) }}円</td>
                <td class="px-4 py-3 text-right">{{ fmt(d.copayment_amount) }}円</td>
                <td class="px-4 py-3 text-right font-semibold">{{ fmt(d.adjusted_amount) }}円</td>
                <td class="px-4 py-3 text-center">{{ d.is_managing_facility ? '○' : '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
