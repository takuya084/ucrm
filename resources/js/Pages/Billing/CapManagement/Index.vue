<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  rows:      Array,
  yearMonth: String,
  labels:    Object,
})

const selectedMonth = ref(props.yearMonth)
const fmt = (n) => (n == null ? '—' : Number(n).toLocaleString() + '円')
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('ja-JP') : '—'

const STATUS_COLORS = {
  draft:     'bg-gray-100 text-gray-500',
  created:   'bg-blue-100 text-blue-700',
  sent:      'bg-amber-100 text-amber-700',
  received:  'bg-purple-100 text-purple-700',
  confirmed: 'bg-green-100 text-green-700',
}

const CONTRACT_COLORS = {
  contracted: 'text-gray-700',
  pending:    'text-amber-600',
  terminated: 'text-gray-400 line-through',
}

const filterStatus = ref('all')
const filtered = computed(() => {
  if (filterStatus.value === 'all') return props.rows
  return props.rows.filter((r) => r.status === filterStatus.value)
})

const calculate = () => {
  if (!confirm(`${selectedMonth.value} の上限管理を計算しますか？（確定済は再計算されません）`)) return
  Inertia.post(route('billing.cap-management.calculate'), { year_month: selectedMonth.value })
}

const changeMonth = () => {
  Inertia.get(route('billing.cap-management.index'), { month: selectedMonth.value }, { preserveState: true, replace: true })
}

const transition = (row, action, label) => {
  if (!row.management_id) return
  if (!confirm(`${row.child_name} を「${label}」にしますか？`)) return
  Inertia.post(route('billing.cap-management.transition', row.management_id), { action })
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
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">年月</label>
            <input v-model="selectedMonth" type="month" @change="changeMonth" class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">状態</label>
            <select v-model="filterStatus" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
              <option value="all">すべて</option>
              <option v-for="(label, key) in labels.status" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <button @click="calculate" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">
            計算実行
          </button>
          <a :href="route('billing.cap-management.export', { year_month: selectedMonth })"
            class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600">
            CSV出力
          </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
          <div v-if="filtered.length === 0" class="py-12 text-center text-gray-400 text-sm">
            対象の利用者がいません
          </div>
          <table v-else class="w-full text-sm min-w-[1100px]">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-3 py-2 text-left">利用者</th>
                <th class="px-3 py-2 text-left">契約</th>
                <th class="px-3 py-2 text-left">様式</th>
                <th class="px-3 py-2 text-left">実績確定</th>
                <th class="px-3 py-2 text-left">作成状態</th>
                <th class="px-3 py-2 text-left">関連事業所</th>
                <th class="px-3 py-2 text-left">送付</th>
                <th class="px-3 py-2 text-left">受領</th>
                <th class="px-3 py-2 text-right">管理結果額</th>
                <th class="px-3 py-2 text-left">備考</th>
                <th class="px-3 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="row in filtered" :key="row.child_id" class="hover:bg-gray-50">
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-900">{{ row.child_name }}</div>
                  <div v-if="row.child_name_kana" class="text-xs text-gray-400">{{ row.child_name_kana }}</div>
                </td>
                <td :class="['px-3 py-2 text-xs', CONTRACT_COLORS[row.contract_status]]">
                  {{ labels.contractStatus[row.contract_status] }}
                </td>
                <td class="px-3 py-2 text-xs text-gray-600">{{ labels.formType[row.form_type] }}</td>
                <td class="px-3 py-2 text-xs">{{ fmtDate(row.actual_confirmed_at) }}</td>
                <td class="px-3 py-2">
                  <span :class="['text-xs px-2 py-0.5 rounded-full', STATUS_COLORS[row.status]]">
                    {{ labels.status[row.status] }}
                  </span>
                </td>
                <td class="px-3 py-2 text-xs text-gray-600">{{ row.related_count }} 事業所</td>
                <td class="px-3 py-2 text-xs">{{ fmtDate(row.sent_at) }}</td>
                <td class="px-3 py-2 text-xs">{{ fmtDate(row.received_at) }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ fmt(row.result_amount) }}</td>
                <td class="px-3 py-2 text-xs text-gray-500 max-w-[140px] truncate" :title="row.remarks">{{ row.remarks ?? '—' }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">
                  <Link v-if="row.management_id" :href="route('billing.cap-management.show', row.management_id)"
                    class="text-xs text-indigo-600 hover:underline mr-2">詳細</Link>
                  <button v-if="row.status === 'created'" @click="transition(row, 'send', '送付済')"
                    class="text-xs px-2 py-0.5 bg-amber-500 text-white rounded hover:bg-amber-600">送付</button>
                  <button v-else-if="row.status === 'sent'" @click="transition(row, 'receive', '受領済')"
                    class="text-xs px-2 py-0.5 bg-purple-500 text-white rounded hover:bg-purple-600">受領</button>
                  <button v-else-if="row.status === 'received'" @click="transition(row, 'confirm', '確定済')"
                    class="text-xs px-2 py-0.5 bg-green-500 text-white rounded hover:bg-green-600">確定</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
