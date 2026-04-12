<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  grouped:   Array,
  yearMonth: String,
  childId:   [Number, String, null],
  children:  Array,
})

const selectedMonth = ref(props.yearMonth)
const selectedChild = ref(props.childId ?? '')

const applyFilter = () => {
  Inertia.get(route('billing.daily-records.index'), {
    month:    selectedMonth.value || undefined,
    child_id: selectedChild.value || undefined,
  }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="実績記録票" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">実績記録票</h2>
        <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- フィルター -->
        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">年月</label>
            <input v-model="selectedMonth" type="month" @change="applyFilter" class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">児童</label>
            <select v-model="selectedChild" @change="applyFilter" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <!-- 児童別実績 -->
        <div v-for="group in grouped" :key="group.child.id" class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">{{ group.child.name }}（{{ group.child.name_kana }}）</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                  <th class="px-3 py-2 text-left">日付</th>
                  <th class="px-3 py-2 text-left">状態</th>
                  <th class="px-3 py-2 text-center">来所</th>
                  <th class="px-3 py-2 text-center">退所</th>
                  <th class="px-3 py-2 text-center">学校日</th>
                  <th class="px-3 py-2 text-center">送迎</th>
                  <th class="px-3 py-2 text-left">サービスコード</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="rec in group.records" :key="rec.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 text-xs">{{ rec.date }}</td>
                  <td class="px-3 py-2 text-xs">
                    <span :class="rec.status === 'attended' ? 'text-green-600' : 'text-orange-500'">
                      {{ rec.status === 'attended' ? '出席' : '欠席(連絡)' }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center text-xs">{{ rec.check_in_time ?? '-' }}</td>
                  <td class="px-3 py-2 text-center text-xs">{{ rec.check_out_time ?? '-' }}</td>
                  <td class="px-3 py-2 text-center text-xs">{{ rec.is_school_day ? '○' : '休' }}</td>
                  <td class="px-3 py-2 text-center text-xs">
                    <span v-if="rec.pickup_done">迎</span>
                    <span v-if="rec.dropoff_done"> 送</span>
                    <span v-if="!rec.pickup_done && !rec.dropoff_done">-</span>
                  </td>
                  <td class="px-3 py-2">
                    <div v-for="dsr in rec.daily_service_records" :key="dsr.id" class="text-xs text-gray-600">
                      <span class="font-mono">{{ dsr.service_code }}</span> {{ dsr.service_name }} ({{ dsr.units }}単位)
                    </div>
                    <span v-if="!rec.daily_service_records?.length" class="text-xs text-gray-400">未計算</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="grouped.length === 0" class="bg-white shadow-sm rounded-lg py-12 text-center text-gray-400 text-sm">
          実績データがありません
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
