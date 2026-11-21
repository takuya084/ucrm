<script setup>
import MenuDestination from '@/Components/MenuDestination.vue'
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { allergyNotice } from '@/lib/notice.mjs'

const props = defineProps({ work: Object, date: String, calendarToday: String, visits: Array, absences: Array, absenceAvailable: Boolean, activeChildren: Number, attendanceSummary: Object })
const selectedDate = ref(props.date)
watch(() => props.date, date => { selectedDate.value = date })
const tasks = [
  { key: 'home', label: '家庭の連絡が未確認' },
  { key: 'record', label: '支援記録未入力' },
  { key: 'report', label: '連絡帳未入力' },
  { key: 'draft', label: '連絡帳が下書き' },
  { key: 'delivery', label: '連携の確認が必要' },
]
const changeDate = () => { if (selectedDate.value) router.get(route('dashboard'), { date: selectedDate.value }) }
</script>

<template>
  <section aria-label="業務内容確認" class="daily-workspace space-y-6">
    <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
      <label class="text-sm font-medium text-gray-700">対象日
        <input type="date" v-model="selectedDate" @change="changeDate" class="mt-1 block max-w-full rounded-md border-gray-300" />
      </label>
      <Link v-if="date !== calendarToday" :href="route('dashboard')" class="px-3 py-2 text-sm text-primary-700 underline">今日へ戻る</Link>
    </div>

    <section aria-label="出欠の概要" class="rounded-xl border bg-white p-4">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm"><span>契約中 <strong>{{ activeChildren }}</strong>人</span><span class="text-green-800">{{ attendanceSummary.saved ? '出席' : '出席予定' }} <strong class="text-xl">{{ attendanceSummary.attended }}</strong>人</span><MenuDestination label="出欠・利用実績" :href="route('usage-records.index', { date })" /></div>
      <div class="mt-3 border-t pt-3">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="workspace-section-title text-gray-900">欠席児童</h3><div class="flex flex-wrap gap-4 text-sm"><Link :href="route('absences.index', {date})" class="text-primary-700 underline">欠席連絡・相談記録</Link><Link :href="route('vacancy-adjustment.index', {date})" class="text-primary-700 underline">空き枠調整</Link></div></div>
        <p v-if="!absenceAvailable" role="alert" class="mt-2 text-sm text-red-800">連携先の欠席連絡を確認できません。以下は取得できた出欠のみです。</p>
        <p v-if="!absences.length" class="mt-2 text-sm text-gray-600">欠席の登録はありません。</p>
        <ul v-else class="mt-2 divide-y">
          <li v-for="child in absences" :key="child.child_id" class="py-3 text-sm">
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1"><strong>{{ child.name }}</strong><span>{{ child.reason || '理由未記録' }}</span><span v-if="child.notice" class="rounded-full bg-amber-50 px-2 py-1 text-amber-900">{{ child.notice.assessment.status }}</span></div>
            <p v-if="child.notice" class="mt-1 break-words text-gray-600">連絡 {{ child.notice.payload.contacted_at?.replace('T',' ') || '日時未記録' }} ／ {{ ({tel:'電話',line:'LINE',app:'アプリ',in_person:'対面',email:'メール',other:'その他'})[child.notice.payload.contact_method] || '手段未記録' }} ／ {{ child.notice.payload.caller || '連絡者未記録' }} ／ 受付 {{ child.notice.payload.receiver_name || '未記録' }}</p>
          </li>
        </ul>
      </div>
    </section>

    <nav aria-label="この日の確認順" class="workspace-steps">
      <a href="#daily-visits"><span class="workspace-step-number">01</span><span><strong>利用児童</strong><small>{{ visits.length }}人の予定・家庭の連絡</small></span><b aria-hidden="true">↓</b></a>
      <a href="#transport-work"><span class="workspace-step-number">02</span><span><strong>送迎</strong><small>時刻・場所・担当</small></span><b aria-hidden="true">↓</b></a>
      <a href="#activity-preparation"><span class="workspace-step-number">03</span><span><strong>療育準備</strong><small>活動・担当・準備物</small></span><b aria-hidden="true">↓</b></a>
    </nav>

    <section aria-labelledby="daily-visits" class="overflow-hidden rounded-xl border bg-white">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
        <h3 id="daily-visits" class="workspace-section-title text-gray-900">利用児童 <span class="ml-1 text-primary-700">{{ visits.length }}人</span></h3>
        <MenuDestination label="出欠・利用実績" :href="route('usage-records.index', { date })" />
      </div>
      <p v-if="!visits.length" class="px-4 py-5 text-sm text-gray-700">選択日の利用児童は表示されていません。</p>
      <ul v-else class="daily-visits-list divide-y">
        <li v-for="visit in visits" :key="visit.childId" class="grid gap-x-5 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)]">
          <p class="workspace-child-name break-words font-semibold text-gray-900"><span class="workspace-avatar" aria-hidden="true">{{ visit.name.replace('【架空】', '').trim().slice(0, 1) }}</span>{{ visit.name }}</p>
          <div class="text-sm text-gray-700">
            <p v-if="visit.arrival || visit.departure" class="workspace-time"><span>来所 <b>{{ visit.arrival || '未登録' }}</b></span><span>退所 <b>{{ visit.departure || '未登録' }}</b></span></p>
            <p v-else>来所・退所時刻は未登録</p>
            <p v-if="visit.pickup || visit.dropoff" class="mt-1">送迎予約 {{ visit.pickup ? `迎え ${visit.pickup}` : '' }} {{ visit.dropoff ? `送り ${visit.dropoff}` : '' }}</p>
          </div>
          <div class="space-y-1 text-sm">
            <p v-if="allergyNotice(visit.allergy)" class="workspace-allergy break-words font-medium text-red-800">アレルギー　{{ allergyNotice(visit.allergy) }}</p>
            <p v-if="visit.home" class="font-medium text-gray-700">{{ visit.home }}</p>
          </div>
        </li>
      </ul>
    </section>
    <slot name="after-visits" />
    <section aria-labelledby="pending-heading" class="rounded-xl border bg-white p-4">
      <h3 id="pending-heading" class="workspace-section-title mb-4 text-gray-900">未対応業務</h3>
      <p v-if="!work.hasAttendance && visits.length" class="mb-3 text-sm text-amber-900">出欠が未保存です。記録・連絡帳の未入力は、出欠の保存後に集計されます。</p>
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <Link v-for="task in tasks" :key="task.key" :aria-label="`${task.label} ${work.counts[task.key]}人`" :href="route('contact-notes.index', { date, task: task.key })"
          class="workspace-task-card rounded-lg p-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
          :class="work.counts[task.key] ? 'bg-amber-50 text-amber-900' : 'bg-gray-50 text-gray-600'">
          <span class="block text-sm">{{ task.label }}</span>
          <span class="mt-1 block text-2xl font-bold tabular-nums">{{ work.counts[task.key] }}<span class="ml-1 text-sm font-normal">人</span></span>
        </Link>
      </div>
    </section>

  </section>
</template>
