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
  { key: 'home', label: '家庭の連絡が未確認' },
  { key: 'record', label: '支援記録未入力' },
  { key: 'report', label: '連絡帳未入力' },
  { key: 'draft', label: '連絡帳が下書き' },
  { key: 'delivery', label: '連携の確認が必要' },
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
  <section aria-label="日別の業務" class="daily-workspace space-y-6">
    <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
      <label class="text-sm font-medium text-gray-700">
        対象日
        <input type="date" v-model="selectedDate" @change="changeDate" class="block mt-1 max-w-full border-gray-300 rounded-md" />
      </label>
      <Link v-if="date !== calendarToday" :href="route('dashboard')" class="px-3 py-2 text-sm text-primary-700 underline">今日へ戻る</Link>
    </div>

    <section aria-label="未対応のまとめ" class="rounded-xl border bg-white p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold text-gray-900">この日の未対応</h3>
        <a href="#pending-work" class="text-sm text-primary-700 underline">児童別の状況</a>
      </div>
      <p v-if="!work.hasAttendance && visits.length" class="mb-3 text-sm text-amber-900">出欠が未保存です。記録・連絡帳の未入力は、出欠の保存後に集計されます。</p>
      <dl class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div v-for="task in tasks" :key="task.key" class="rounded-lg p-3" :class="work.counts[task.key] ? 'bg-amber-50 text-amber-900' : 'bg-gray-50 text-gray-600'">
          <dt class="text-sm">{{ task.label }}</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ work.counts[task.key] }}<span class="ml-1 text-sm font-normal">人</span></dd>
        </div>
      </dl>
    </section>

    <nav aria-label="この日の確認順" class="workspace-steps">
      <a href="#daily-visits"><span class="workspace-step-number">01</span><span><strong>利用児童</strong><small>{{ visits.length }}人の予定・家庭の連絡</small></span><b aria-hidden="true">↓</b></a>
      <a href="#transport-work"><span class="workspace-step-number">02</span><span><strong>スタッフ全員の送迎</strong><small>時刻・場所・担当</small></span><b aria-hidden="true">↓</b></a>
      <a href="#activity-preparation"><span class="workspace-step-number">03</span><span><strong>療育準備</strong><small>活動・担当・準備物</small></span><b aria-hidden="true">↓</b></a>
    </nav>

    <section aria-labelledby="daily-visits" class="overflow-hidden rounded-xl border bg-white">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
        <div>
          <h3 id="daily-visits" class="workspace-section-title font-semibold text-gray-900">利用児童 <span class="ml-1 text-primary-700">{{ visits.length }}人</span></h3>
          <p class="mt-1 text-xs text-gray-600">{{ sourceLabels[attendanceSource] }}</p>
        </div>
        <span class="text-xs text-gray-600">入力先：メニュー「出欠・利用実績」</span>
      </div>

      <p v-if="visits.length === 0" class="px-4 py-5 text-sm text-gray-700">選択日の利用児童は表示されていません。メニュー「出欠・利用実績」で予定を確認できます。</p>
      <ul v-else class="daily-visits-list divide-y">
        <li v-for="visit in visits" :key="visit.childId" class="grid gap-x-5 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)]">
          <p class="workspace-child-name break-words font-semibold text-gray-900"><span class="workspace-avatar" aria-hidden="true">{{ visit.name.replace('【架空】', '').trim().slice(0, 1) }}</span>{{ visit.name }}</p>
          <div class="text-sm text-gray-700">
            <p v-if="visit.arrival || visit.departure" class="workspace-time"><span>来所 <b>{{ visit.arrival || '未登録' }}</b></span><span>退所 <b>{{ visit.departure || '未登録' }}</b></span></p>
            <p v-else>来所・退所時刻は未登録</p>
            <p v-if="visit.pickup || visit.dropoff" class="mt-1">送迎予約 {{ visit.pickup ? `迎え ${visit.pickup}` : '' }} {{ visit.dropoff ? `送り ${visit.dropoff}` : '' }}</p>
          </div>
          <div class="space-y-1 text-sm">
            <p v-if="visit.allergy" class="workspace-allergy break-words font-medium text-red-800">アレルギー　{{ visit.allergy }}</p>
            <p v-if="visit.home" class="font-medium text-gray-700">{{ visit.home }}</p>
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
      <div class="border-b bg-gray-50 px-4 py-3">
        <p class="text-sm text-gray-700">入力・確認先：メニュー「記録・連絡帳」</p>

      </div>
      <p v-if="pendingRows.length === 0" class="px-4 py-5 text-sm text-gray-700">
        {{ work.hasAttendance ? '現在、対応が必要な児童はいません。' : '現在、家庭の連絡などに未対応の項目はありません。' }}
      </p>
      <ul v-else class="divide-y">
        <li v-for="row in pendingRows" :key="row.child_id"
          class="flex flex-wrap items-center justify-between gap-x-5 gap-y-3 p-4" :aria-label="row.child_name + 'の業務'">
          <div>
            <p class="break-words font-semibold text-gray-900">{{ row.child_name }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ row.attendance }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span v-for="action in row.actions" :key="action.key"
              class="rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-900">
              {{ action.label }}
            </span>
          </div>
        </li>
      </ul>
    </section>

    <details v-if="work.rows.length" class="rounded-xl border bg-white" :open="showAll" @toggle="showAll = $event.target.open">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800">全児童の状況（{{ work.rows.length }}人）</summary>
      <ul v-if="showAll" class="divide-y border-t">
        <li v-for="row in work.rows" :key="row.child_id" class="space-y-2 p-4">
          <p class="break-words font-semibold text-gray-900">{{ row.child_name }}</p>
          <p class="text-sm text-gray-700">{{ row.attendance }}・{{ row.transport }}・支援記録 {{ row.record }}</p>
          <p class="text-sm text-gray-700">{{ row.state.home }}</p>
          <ContactNoteStatus :state="row.state" />

        </li>
      </ul>
    </details>
  </section>
</template>
