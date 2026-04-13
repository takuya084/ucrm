<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'
import { reactive } from 'vue'

const props = defineProps({ management: Object })
const fmt = (n) => Number(n ?? 0).toLocaleString()
const RESULT_LABEL = { '1': '管理結果なし', '2': '管理結果あり', '3': '管理結果あり（按分）' }

const EXTERNAL_TYPE = 'App\\Models\\ExternalFacility'
const isExternal = (d) => d.billable_facility_type === EXTERNAL_TYPE

const editing = reactive({})

const startEdit = (d) => {
  editing[d.id] = {
    total_amount:     d.total_amount,
    copayment_amount: d.copayment_amount,
  }
}
const cancelEdit = (d) => {
  delete editing[d.id]
}
const saveEdit = (d) => {
  Inertia.patch(
    route('billing.cap-management.details.update', {
      copaymentCapManagement: props.management.id,
      copaymentCapDetail: d.id,
    }),
    editing[d.id],
    { onSuccess: () => delete editing[d.id] }
  )
}
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
        <FlashMessage />

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
            <p class="text-xs text-gray-400 mt-1">他社事業所（外）の金額は編集できます。更新すると按分が再計算されます。</p>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">事業所名</th>
                <th class="px-4 py-2 text-right">総費用額</th>
                <th class="px-4 py-2 text-right">利用者負担額</th>
                <th class="px-4 py-2 text-right">調整後負担額</th>
                <th class="px-4 py-2 text-center">管理</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="d in management.details" :key="d.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  {{ d.facility_name }}
                  <span v-if="d.is_managing_facility" class="ml-1 text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">管理</span>
                  <span v-if="isExternal(d)" class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">外</span>
                </td>

                <template v-if="editing[d.id]">
                  <td class="px-4 py-2 text-right">
                    <input type="number" min="0" v-model.number="editing[d.id].total_amount"
                      class="w-28 text-right border border-gray-300 rounded px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-2 text-right">
                    <input type="number" min="0" v-model.number="editing[d.id].copayment_amount"
                      class="w-28 text-right border border-gray-300 rounded px-2 py-1 text-sm" />
                  </td>
                  <td class="px-4 py-3 text-right text-gray-400">—</td>
                  <td class="px-4 py-3 text-center">{{ d.is_managing_facility ? '○' : '' }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button @click="saveEdit(d)" class="text-xs px-2 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600 mr-1">保存</button>
                    <button @click="cancelEdit(d)" class="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50">取消</button>
                  </td>
                </template>

                <template v-else>
                  <td class="px-4 py-3 text-right">{{ fmt(d.total_amount) }}円</td>
                  <td class="px-4 py-3 text-right">{{ fmt(d.copayment_amount) }}円</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ fmt(d.adjusted_amount) }}円</td>
                  <td class="px-4 py-3 text-center">{{ d.is_managing_facility ? '○' : '' }}</td>
                  <td class="px-4 py-3 text-right">
                    <button v-if="isExternal(d)" @click="startEdit(d)"
                      class="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600">編集</button>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
