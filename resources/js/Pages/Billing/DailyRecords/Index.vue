<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, computed, watch } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  grouped:   Array,
  yearMonth: String,
  childId:   [Number, String, null],
  children:  Array,
  locked:    Boolean,
})

const selectedMonth = ref(props.yearMonth)
const selectedChild = ref(props.childId ?? '')

const applyFilter = () => {
  Inertia.get(route('billing.daily-records.index'), {
    month:    selectedMonth.value || undefined,
    child_id: selectedChild.value || undefined,
  }, { preserveState: true, replace: true })
}

// 編集用ローカルコピー（record.id → 編集可能フィールド）
const buildEditable = () => {
  const map = {}
  for (const group of props.grouped) {
    for (const rec of group.records) {
      map[rec.id] = {
        usage_record_id: rec.id,
        check_in_time:   rec.check_in_time ?? '',
        check_out_time:  rec.check_out_time ?? '',
        is_school_day:   rec.is_school_day,
        service_type:    rec.service_type ?? '',
      }
    }
  }
  return map
}

const editable = ref(buildEditable())
const original = ref(JSON.parse(JSON.stringify(editable.value)))

watch(() => props.grouped, () => {
  editable.value = buildEditable()
  original.value = JSON.parse(JSON.stringify(editable.value))
}, { deep: false })

const isDirty = (id) => JSON.stringify(editable.value[id]) !== JSON.stringify(original.value[id])

const dirtyCount = computed(() =>
  Object.keys(editable.value).filter(id => isDirty(id)).length
)

const save = () => {
  const changed = Object.values(editable.value)
    .filter(r => isDirty(r.usage_record_id))
    .map(r => ({
      usage_record_id: r.usage_record_id,
      check_in_time:   r.check_in_time || null,
      check_out_time:  r.check_out_time || null,
      is_school_day:   r.is_school_day,
      service_type:    r.service_type || null,
    }))
  if (changed.length === 0) return
  if (!confirm(`${changed.length}件の実績を更新します。\n更新後、この月の請求が計算済みの場合は「月次請求」から再計算を実行してください。`)) return
  Inertia.post(route('billing.daily-records.bulk-update'), { records: changed })
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

        <!-- フィルター + 保存 -->
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
          <div class="ml-auto flex items-center gap-3">
            <span v-if="locked" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full font-medium">
              請求確定済みのため編集不可
            </span>
            <template v-else>
              <span v-if="dirtyCount > 0" class="text-xs text-amber-600 font-medium">{{ dirtyCount }}件 未保存</span>
              <button @click="save" :disabled="dirtyCount === 0"
                class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 disabled:opacity-40 transition">
                変更を保存
              </button>
            </template>
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
                  <th class="px-3 py-2 text-center">種別</th>
                  <th class="px-3 py-2 text-center">送迎</th>
                  <th class="px-3 py-2 text-left">サービスコード</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="rec in group.records" :key="rec.id"
                  :class="['hover:bg-gray-50', !locked && isDirty(rec.id) ? 'bg-amber-50/60' : '']">
                  <td class="px-3 py-2 text-xs whitespace-nowrap">{{ rec.date }}</td>
                  <td class="px-3 py-2 text-xs">
                    <span :class="rec.status === 'attended' ? 'text-green-600' : 'text-orange-500'">
                      {{ rec.status === 'attended' ? '出席' : '欠席(連絡)' }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center text-xs">
                    <input v-if="!locked" v-model="editable[rec.id].check_in_time" type="time"
                      class="border border-gray-300 rounded px-1.5 py-1 text-xs w-24" />
                    <span v-else>{{ rec.check_in_time ?? '-' }}</span>
                  </td>
                  <td class="px-3 py-2 text-center text-xs">
                    <input v-if="!locked" v-model="editable[rec.id].check_out_time" type="time"
                      class="border border-gray-300 rounded px-1.5 py-1 text-xs w-24" />
                    <span v-else>{{ rec.check_out_time ?? '-' }}</span>
                  </td>
                  <td class="px-3 py-2 text-center text-xs">
                    <input v-if="!locked" v-model="editable[rec.id].is_school_day" type="checkbox" class="w-3.5 h-3.5" />
                    <span v-else>{{ rec.is_school_day ? '○' : '休' }}</span>
                  </td>
                  <td class="px-3 py-2 text-center text-xs">
                    <select v-if="!locked" v-model="editable[rec.id].service_type"
                      class="border border-gray-300 rounded px-1 py-1 text-xs">
                      <option value="">自動</option>
                      <option value="houday">放デイ</option>
                      <option value="jidou">児発</option>
                    </select>
                    <span v-else>{{ rec.service_type === 'houday' ? '放デイ' : rec.service_type === 'jidou' ? '児発' : '自動' }}</span>
                  </td>
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
