<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ContactNoteStatus from '@/Components/ContactNoteStatus.vue'

const props = defineProps({
  work: Object,
  date: String,
  calendarToday: String,
  visits: Array,
  attendanceSource: String,
})
const sourceLabels = { records: '保存済みの出欠', yoyaku: '予約情報', schedule: '固定の利用予定' }
const selectedDate = ref(props.date)
const showAll = ref(false)

watch(() => props.date, (date) => {
  selectedDate.value = date
  showAll.value = false
})

const tasks = [
  { key: 'home', label: '家庭の連絡を確認', url: (row) => row.note_url },
  { key: 'record', label: '支援記録を入力', url: (row) => row.record_url },
  { key: 'report', label: '連絡帳を入力', url: (row) => row.record_url },
  { key: 'draft', label: '連絡帳の公開を確認', url: (row) => row.note_url },
  { key: 'delivery', label: '連携状況を確認', url: (row) => row.note_url },
]

const pendingRows = computed(() => props.work.rows
  .map((row) => ({ ...row, actions: tasks.filter((task) => row.tasks[task.key]) }))
  .filter((row) => row.actions.length > 0)
  .sort((a, b) => Number(b.tasks.home) - Number(a.tasks.home)))

const changeDate = () => {
  if (selectedDate.value) router.get(route('dashboard'), { date: selectedDate.value })
}
</script>

<template>
  <section aria-label="日別の業務" class="space-y-4">
    <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
      <label class="text-sm font-medium text-gray-700">
        対象日
        <input type="date" v-model="selectedDate" @change="changeDate" class="block mt-1 max-w-full border-gray-300 rounded-md" />
      </label>
      <Link v-if="date !== calendarToday" :href="route('dashboard')" class="px-3 py-2 text-sm text-primary-700 underline">今日へ戻る</Link>
    </div>

    <section aria-labelledby="daily-visits" class="overflow-hidden rounded-xl border bg-white">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
        <div>
          <h3 id="daily-visits" class="font-semibold text-gray-900">利用児童 <span class="ml-1 text-primary-700">{{ visits.length }}人</span></h3>
          <p class="mt-1 text-xs text-gray-600">{{ sourceLabels[attendanceSource] }}</p>
        </div>
        <Link :href="route('usage-records.index', { date })" class="text-sm font-medium text-primary-700 underline">出欠・送迎を開く</Link>
      </div>

      <p v-if="visits.length === 0" class="px-4 py-5 text-sm text-gray-700">選択日の利用児童は表示されていません。出欠・送迎画面で予定を確認できます。</p>
      <ul v-else class="divide-y">
        <li v-for="visit in visits" :key="visit.childId" class="grid gap-x-5 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)]">
          <Link :href="route('children.show', visit.childId)" class="break-words font-semibold text-primary-800 hover:underline">{{ visit.name }}</Link>
          <div class="text-sm text-gray-700">
            <p v-if="visit.arrival || visit.departure">来所 {{ visit.arrival || '未登録' }} ／ 退所 {{ visit.departure || '未登録' }}</p>
            <p v-else>来所・退所時刻は未登録</p>
            <p v-if="visit.pickup || visit.dropoff" class="mt-1">送迎予約 {{ visit.pickup ? `迎え ${visit.pickup}` : '' }} {{ visit.dropoff ? `送り ${visit.dropoff}` : '' }}</p>
          </div>
          <div class="space-y-1 text-sm">
            <p v-if="visit.allergy" class="break-words font-medium text-red-800">アレルギー　{{ visit.allergy }}</p>
            <Link v-if="visit.home" :href="route('contact-notes.index', { date }) + '#child-' + visit.childId"
              class="inline-block font-medium text-primary-700 underline">{{ visit.home }}</Link>
          </div>
        </li>
      </ul>
    </section>

    <slot name="after-visits" />

    <section aria-labelledby="pending-work" class="overflow-hidden rounded-xl border bg-white">
      <div class="flex flex-wrap items-baseline justify-between gap-2 border-b px-4 py-3">
        <h3 id="pending-work" class="font-semibold text-gray-900">対応が必要な児童</h3>
        <span class="text-sm text-gray-600">{{ pendingRows.length }}人</span>
      </div>
      <p v-if="pendingRows.length === 0" class="px-4 py-5 text-sm text-gray-700">
        {{ work.hasAttendance ? '現在、対応が必要な児童はいません。' : '現在、家庭の連絡などに未対応の項目はありません。' }}
      </p>
      <ul v-else class="divide-y">
        <li v-for="row in pendingRows" :key="row.child_id"
          class="flex flex-wrap items-center justify-between gap-x-5 gap-y-3 p-4" :aria-label="row.child_name + 'の業務'">
          <div>
            <Link :href="route('children.show', row.child_id)" class="break-words font-semibold text-gray-900 hover:underline">{{ row.child_name }}</Link>
            <p class="mt-1 text-sm text-gray-600">{{ row.attendance }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Link v-for="(action, index) in row.actions" :key="action.key" :href="action.url(row)"
              class="rounded-md border px-3 py-2 text-sm font-medium"
              :class="index === 0 ? 'border-primary-600 bg-primary-600 text-white hover:bg-primary-700' : 'border-gray-300 text-gray-800 hover:bg-gray-50'">
              {{ action.label }}
            </Link>
          </div>
        </li>
      </ul>
    </section>

    <details v-if="work.rows.length" class="rounded-xl border bg-white" :open="showAll" @toggle="showAll = $event.target.open">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800">全児童の状況（{{ work.rows.length }}人）</summary>
      <ul v-if="showAll" class="divide-y border-t">
        <li v-for="row in work.rows" :key="row.child_id" class="space-y-2 p-4">
          <Link :href="route('children.show', row.child_id)" class="break-words font-semibold text-primary-800 underline">{{ row.child_name }}</Link>
          <p class="text-sm text-gray-700">{{ row.attendance }}・{{ row.transport }}・支援記録 {{ row.record }}</p>
          <p class="text-sm text-gray-700">{{ row.state.home }}</p>
          <ContactNoteStatus :state="row.state" />
          <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            <Link :href="row.note_url" class="text-primary-700 underline">連絡帳を確認</Link>
            <Link v-if="row.record_url" :href="row.record_url" class="text-primary-700 underline">
              {{ row.record === '保存済み' ? '記録・連絡帳を編集' : '記録・連絡帳を入力' }}
            </Link>
          </div>
        </li>
      </ul>
    </details>
  </section>
</template>
