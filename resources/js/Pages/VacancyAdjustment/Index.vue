<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  date:           String,
  dayName:        String,
  stats:          Object,
  absentChildren: Array,
  candidates:     Array,
})

const selectedDate = ref(props.date)
const goToDate = () => {
  Inertia.get(route('vacancy-adjustment.index'), { date: selectedDate.value }, { preserveState: false })
}

const DAY_LABELS = { mon: '月', tue: '火', wed: '水', thu: '木', fri: '金', sat: '土', sun: '日' }
const DAY_ORDER  = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

const RELATION_LABELS = {
  father: '父', mother: '母', grandfather: '祖父', grandmother: '祖母', other: 'その他'
}
const CONTACT_LABELS = { tel: '電話', line: 'LINE', email: 'メール', in_person: '直接' }
const CONTACT_COLORS = {
  tel:       'bg-blue-100 text-blue-700',
  line:      'bg-green-100 text-green-700',
  email:     'bg-purple-100 text-purple-700',
  in_person: 'bg-gray-100 text-gray-700',
}

const STATUS_LABELS = {
  absent:        { label: '無断欠席', color: 'bg-red-100 text-red-700' },
  absent_notice: { label: '欠席連絡済', color: 'bg-yellow-100 text-yellow-700' },
  cancel:        { label: 'キャンセル', color: 'bg-gray-100 text-gray-600' },
  not_recorded:  { label: '未記録',    color: 'bg-orange-100 text-orange-700' },
}

const remainingColor = (days) => {
  if (days >= 10) return 'text-green-700 bg-green-50 border-green-200'
  if (days >= 5)  return 'text-yellow-700 bg-yellow-50 border-yellow-200'
  return 'text-orange-700 bg-orange-50 border-orange-200'
}

const prevDay = () => {
  selectedDate.value = new Date(new Date(selectedDate.value).getTime() - 86400000).toISOString().slice(0, 10)
  goToDate()
}
const nextDay = () => {
  selectedDate.value = new Date(new Date(selectedDate.value).getTime() + 86400000).toISOString().slice(0, 10)
  goToDate()
}
</script>

<template>
  <Head title="空き枠調整" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4 flex-wrap">
        <h2 class="font-semibold text-xl text-gray-800">空き枠調整</h2>
        <div class="flex items-center gap-2">
          <button @click="prevDay" class="px-2 py-1 border rounded text-sm hover:bg-gray-100">◀</button>
          <input
            v-model="selectedDate"
            type="date"
            class="border border-gray-300 rounded px-2 py-1 text-sm"
            @change="goToDate"
          />
          <span class="text-sm font-medium text-indigo-600">（{{ dayName }}曜日）</span>
          <button @click="nextDay" class="px-2 py-1 border rounded text-sm hover:bg-gray-100">▶</button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- サマリーバー -->
        <div class="grid grid-cols-5 gap-3">
          <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-gray-700">{{ stats.capacity }}</div>
            <div class="text-xs text-gray-400 mt-1">定員</div>
          </div>
          <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-gray-700">{{ stats.scheduled }}</div>
            <div class="text-xs text-gray-400 mt-1">本日予定</div>
          </div>
          <div class="bg-green-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-green-700">{{ stats.attended }}</div>
            <div class="text-xs text-gray-400 mt-1">出席</div>
          </div>
          <div class="bg-red-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-red-600">{{ stats.absent }}</div>
            <div class="text-xs text-gray-400 mt-1">欠席</div>
          </div>
          <div :class="[
            'rounded-lg shadow-sm p-4 text-center',
            stats.availableSlots > 0 ? 'bg-indigo-50' : 'bg-gray-50'
          ]">
            <div :class="['text-2xl font-bold', stats.availableSlots > 0 ? 'text-indigo-700' : 'text-gray-400']">
              {{ stats.availableSlots }}
            </div>
            <div class="text-xs text-gray-400 mt-1">空き枠</div>
          </div>
        </div>

        <div v-if="stats.availableSlots === 0 && absentChildren.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-400">
          本日は空き枠がなく、欠席者もいません
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- 欠席者リスト -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700">
                本日の欠席者
                <span v-if="absentChildren.length" class="ml-2 px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">
                  {{ absentChildren.length }}名
                </span>
              </h3>
              <Link
                :href="route('usage-records.index', { date })"
                class="text-xs text-indigo-500 hover:underline"
              >出席管理を開く →</Link>
            </div>

            <div v-if="absentChildren.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
              欠席者はいません
            </div>

            <ul v-else class="divide-y">
              <li v-for="child in absentChildren" :key="child.id" class="px-4 py-3">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <Link :href="route('children.show', child.id)" class="font-medium text-gray-900 hover:text-indigo-600 text-sm">
                        {{ child.name }}
                      </Link>
                      <span class="text-xs text-gray-400">{{ child.grade }}</span>
                      <span :class="['text-xs px-2 py-0.5 rounded-full', STATUS_LABELS[child.status]?.color]">
                        {{ STATUS_LABELS[child.status]?.label }}
                      </span>
                      <span v-if="child.pickup_required" class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">送迎</span>
                    </div>
                    <div v-if="child.absent_reason" class="text-xs text-gray-500 mt-0.5">理由：{{ child.absent_reason }}</div>
                  </div>
                </div>
                <!-- 保護者連絡先 -->
                <div v-if="child.guardian" class="mt-2 flex items-center gap-3 text-xs text-gray-600">
                  <span>{{ child.guardian.name }}（{{ RELATION_LABELS[child.guardian.relationship] ?? child.guardian.relationship }}）</span>
                  <a
                    v-if="child.guardian.tel_primary"
                    :href="`tel:${child.guardian.tel_primary}`"
                    class="flex items-center gap-1 text-blue-600 font-medium hover:underline"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    {{ child.guardian.tel_primary }}
                  </a>
                  <span :class="['px-1.5 py-0.5 rounded text-xs', CONTACT_COLORS[child.guardian.preferred_contact]]">
                    {{ CONTACT_LABELS[child.guardian.preferred_contact] }}希望
                  </span>
                </div>
                <div v-else class="mt-1 text-xs text-gray-400">保護者未登録</div>
              </li>
            </ul>
          </div>

          <!-- 連絡候補リスト -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b">
              <h3 class="text-sm font-semibold text-gray-700">
                穴埋め連絡候補
                <span class="ml-1 text-xs text-gray-400 font-normal">（今日スケジュールなし・今月残日数あり）</span>
              </h3>
              <p class="text-xs text-gray-400 mt-0.5">残日数の多い順に表示</p>
            </div>

            <div v-if="candidates.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
              連絡候補はいません
            </div>

            <ul v-else class="divide-y">
              <li v-for="child in candidates" :key="child.id" class="px-4 py-3">
                <div class="flex items-start gap-3">
                  <!-- 残日数バッジ -->
                  <div :class="['flex-shrink-0 w-14 text-center py-1.5 rounded-lg border text-xs font-bold', remainingColor(child.remaining_days)]">
                    <div class="text-lg leading-none">{{ child.remaining_days }}</div>
                    <div class="mt-0.5 opacity-70">残日数</div>
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <Link :href="route('children.show', child.id)" class="font-medium text-gray-900 hover:text-indigo-600 text-sm">
                        {{ child.name }}
                      </Link>
                      <span class="text-xs text-gray-400">{{ child.grade }}</span>
                      <span v-if="child.pickup_required" class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">送迎</span>
                    </div>

                    <!-- 通常スケジュール -->
                    <div class="flex items-center gap-1 mt-1">
                      <span class="text-xs text-gray-400">通常：</span>
                      <span
                        v-for="d in DAY_ORDER"
                        :key="d"
                        :class="[
                          'text-xs w-5 h-5 flex items-center justify-center rounded-full',
                          child.schedule_days.includes(d)
                            ? 'bg-indigo-500 text-white font-medium'
                            : 'bg-gray-100 text-gray-300'
                        ]"
                      >{{ DAY_LABELS[d] }}</span>
                    </div>

                    <!-- 保護者連絡先 -->
                    <div v-if="child.guardian" class="mt-1.5 flex items-center gap-2 flex-wrap text-xs text-gray-600">
                      <span>{{ child.guardian.name }}（{{ RELATION_LABELS[child.guardian.relationship] ?? child.guardian.relationship }}）</span>
                      <a
                        v-if="child.guardian.tel_primary"
                        :href="`tel:${child.guardian.tel_primary}`"
                        class="flex items-center gap-1 text-blue-600 font-medium hover:underline"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        {{ child.guardian.tel_primary }}
                      </a>
                      <span :class="['px-1.5 py-0.5 rounded', CONTACT_COLORS[child.guardian.preferred_contact]]">
                        {{ CONTACT_LABELS[child.guardian.preferred_contact] }}希望
                      </span>
                    </div>
                    <div v-else class="mt-1 text-xs text-gray-400">保護者未登録</div>

                    <!-- 今月の利用状況 -->
                    <div class="mt-1 text-xs text-gray-400">
                      今月：{{ child.used_days }} / {{ child.monthly_limit }}日利用済
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>

      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
