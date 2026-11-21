<script setup>
import MenuDestination from '@/Components/MenuDestination.vue'

import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head } from '@inertiajs/vue3'
import DailyWorkBoard from '@/Components/DailyWorkBoard.vue'
import DailyTransportBoard from '@/Components/DailyTransportBoard.vue'

const props = defineProps({
  absences: Array, absenceAvailable: Boolean, attendanceSummary: Object,
  dailyWork: Object,
  visits: Array,
  attendanceSource: String,
  canManage: Boolean,
  activities: Array,
  activityCategories: Object,
  transportAssignments: Array,
  transportStaff: Array,
  transportVisits: Array,
  transportState: String,
  localTransportCount: Number,
  date: String,
  calendarToday: String,
  todayStats:            Object,
  activeChildren:        Number,
  expiringCertificates:  Array,
  monitoringDue:         Array,
  pendingAgreements:     Array,
  openInquiries:         Array,
  billingMonth:          String,
  billingPeriod:         Object,
  officeTasks: { type: Array, default: () => [] },
})

const officeRoutes = { invoices: 'billing.invoices.index', returns: 'billing.returns.index', claims: 'billing.error-claims.index' }

const daysUntil = (dateStr) => {
  const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000)
  return diff
}

// 前月分の請求業務ガイド（毎月1〜10日は請求期間として強調）
const isBillingWindow = new Date().getDate() <= 10
const BILLING_GUIDE = {
  none:        { label: '未計算',   cls: 'bg-red-100 text-red-700',     text: '請求管理で「計算実行」を押してください。' },
  draft:       { label: '下書き',   cls: 'bg-amber-100 text-amber-700', text: '内容を確認して「確定」してください。' },
  calculating: { label: '計算中',   cls: 'bg-amber-100 text-amber-700', text: '計算が終わるまでお待ちください。' },
  confirmed:   { label: '確定済',   cls: 'bg-blue-100 text-blue-700',   text: 'CSVを出力し、国保連へ伝送してください。' },
  submitted:   { label: '提出済',   cls: 'bg-green-100 text-green-700', text: '支払決定通知が届いたら金額を突合してください。' },
  completed:   { label: '完了',     cls: 'bg-green-200 text-green-800', text: '前月分の請求業務は完了しています。' },
  error:       { label: 'エラー',   cls: 'bg-red-100 text-red-700',     text: '請求管理でエラー内容を確認してください。' },
}
const billingGuide = BILLING_GUIDE[props.billingPeriod?.status ?? 'none'] ?? BILLING_GUIDE.none
</script>

<template>
  <Head title="業務内容確認" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold text-xl text-gray-800">業務内容確認</h2>
        <nav v-if="canManage" aria-label="担当別の業務" class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
          <a href="#staff-work" class="workspace-jump workspace-jump-staff">職員の確認事項</a>
          <a href="#management-work" class="workspace-jump workspace-jump-management">管理者・児発管の確認事項</a>
          <a href="#office-work" class="workspace-jump workspace-jump-office">事務の確認事項</a>
        </nav>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <section id="staff-work" aria-labelledby="staff-heading" class="workspace-role-area workspace-role-staff">
          <div class="workspace-role-header"><h3 id="staff-heading" class="workspace-section-title">職員の確認事項</h3></div>
          <div class="space-y-6 p-4">
        <DailyWorkBoard :work="dailyWork" :date="date" :calendar-today="calendarToday"
          :visits="visits" :absences="absences" :absence-available="absenceAvailable" :active-children="activeChildren" :attendance-summary="attendanceSummary">
          <template #after-visits>
            <div id="transport-work" class="workspace-anchor space-y-2">
              <DailyTransportBoard :key="date" :date="date" :visits="transportVisits"
                :can-manage="false" :assignments="transportAssignments" :staff="[]" :sync-state="transportState" :local-count="localTransportCount">
                <template #header-actions><MenuDestination prefix="入力・変更先：メニュー" label="送迎一覧" :href="route('transport.index', { date })" /></template>
              </DailyTransportBoard>
            </div>
            <section id="activity-preparation" aria-labelledby="activity-preparation-heading" class="workspace-card scroll-mt-4 rounded-xl border bg-white">
              <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                <h3 id="activity-preparation-heading" class="workspace-section-title text-gray-900">療育準備</h3>
                <MenuDestination v-if="canManage" label="療育予定・年間行事" :href="route('daily-activities.index', { date })" />
              </div>
              <p v-if="!activities.length" class="px-4 py-4 text-sm text-gray-700">この日の療育予定は登録されていません。</p>
              <ul v-else class="divide-y">
                <li v-for="activity in activities" :key="activity.id" class="grid gap-2 px-4 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)]">
                  <div class="min-w-0">
                    <p class="text-xs font-medium text-primary-700">{{ activityCategories[activity.category] }}</p>
                    <p class="break-words font-semibold text-gray-900">{{ activity.title }}</p>
                  </div>
                  <p class="text-gray-700">{{ activity.starts_at?.slice(0, 5) || '時刻未定' }} ／ {{ activity.staff?.name || '担当未定' }}</p>
                  <p class="whitespace-pre-wrap break-words text-gray-700">準備物　{{ activity.materials || '記載なし' }}</p>
                </li>
              </ul>
            </section>
          </template>
        </DailyWorkBoard>
          </div>
        </section>

        <section v-if="canManage" id="management-work" aria-labelledby="management-heading" class="workspace-role-area workspace-role-management scroll-mt-4">
          <div class="workspace-role-header"><h3 id="management-heading" class="workspace-section-title">管理者・児発管の確認事項</h3></div>
          <div class="space-y-6 p-4">
            <!-- アラートセクション -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

              <!-- 受給者証 期限アラート -->
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                  <h3 class="workspace-section-title text-sm text-gray-700">
                    受給者証 期限アラート
                    <span v-if="expiringCertificates.length" class="ml-2 px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">
                      {{ expiringCertificates.length }}件
                    </span>
                  </h3>
                </div>
                <div v-if="expiringCertificates.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                  期限切れ・期限間近の受給者証はありません
                </div>
                <ul v-else class="divide-y">
                  <li v-for="cert in expiringCertificates" :key="cert.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-800">
                        {{ cert.child?.name }}
                      </p>
                      <div class="text-xs text-gray-500">有効期限：{{ cert.valid_to }}</div>
                    </div>
                    <span
                      :class="[
                        'text-xs font-medium px-2 py-1 rounded-full',
                        daysUntil(cert.valid_to) <= 7
                          ? 'bg-red-100 text-red-700'
                          : 'bg-amber-100 text-amber-700'
                      ]"
                    >
                      {{ daysUntil(cert.valid_to) < 0 ? `${Math.abs(daysUntil(cert.valid_to))}日超過` : `あと${daysUntil(cert.valid_to)}日` }}
                    </span>
                  </li>
                </ul>
                <p class="border-t bg-gray-50 px-5 py-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" label="受給者証・利用契約" :href="route('children.index', { section: 'cert' })" /></p>
              </div>

              <!-- モニタリング期限アラート -->
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                  <h3 class="workspace-section-title text-sm text-gray-700">
                    モニタリング 実施アラート
                    <span v-if="monitoringDue.length" class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-600 text-xs rounded-full">
                      {{ monitoringDue.length }}件
                    </span>
                  </h3>
                </div>
                <div v-if="monitoringDue.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                  期限の近いモニタリングはありません
                </div>
                <ul v-else class="divide-y">
                  <li v-for="mon in monitoringDue" :key="mon.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-800">
                        {{ mon.child?.name }}
                      </p>
                      <div class="text-xs text-gray-500">次回予定：{{ mon.next_review_date }}</div>
                    </div>
                    <span
                      :class="[
                        'text-xs font-medium px-2 py-1 rounded-full',
                        daysUntil(mon.next_review_date) < 0
                          ? 'bg-red-100 text-red-700'
                          : daysUntil(mon.next_review_date) <= 7
                            ? 'bg-orange-100 text-orange-700'
                            : 'bg-amber-100 text-amber-700'
                      ]"
                    >
                      {{ daysUntil(mon.next_review_date) < 0 ? `${Math.abs(daysUntil(mon.next_review_date))}日超過` : `あと${daysUntil(mon.next_review_date)}日` }}
                    </span>
                  </li>
                </ul>
                <p class="border-t bg-gray-50 px-5 py-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" label="支援計画・モニタリング" :href="route('children.index', { section: 'plan' })" /></p>
              </div>

              <!-- 個別支援計画 同意待ち -->
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b">
                  <h3 class="workspace-section-title text-sm text-gray-700">
                    個別支援計画 同意待ち
                    <span v-if="pendingAgreements.length" class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded-full">
                      {{ pendingAgreements.length }}件
                    </span>
                  </h3>
                </div>
                <div v-if="pendingAgreements.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                  同意待ちの計画書はありません
                </div>
                <ul v-else class="divide-y">
                  <li v-for="plan in pendingAgreements" :key="plan.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-800">
                        {{ plan.child?.name }}
                      </p>
                      <div class="text-xs text-gray-500">{{ plan.valid_from }} 〜 {{ plan.valid_to }}</div>
                    </div>
                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-full">同意待ち</span>
                  </li>
                </ul>
                <p class="border-t bg-gray-50 px-5 py-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" label="支援計画・モニタリング" :href="route('children.index', { section: 'plan' })" /></p>
              </div>

              <!-- 未対応・対応中の問い合わせ -->
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b">
                  <h3 class="workspace-section-title text-sm text-gray-700">
                    対応が必要な問い合わせ
                    <span v-if="openInquiries.length" class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-600 text-xs rounded-full">
                      {{ openInquiries.length }}件
                    </span>
                  </h3>
                </div>
                <div v-if="openInquiries.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                  対応が必要な問い合わせはありません
                </div>
                <ul v-else class="divide-y">
                  <li v-for="inq in openInquiries" :key="inq.id" class="px-5 py-3 flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-800">
                        {{ inq.child?.name }}
                      </p>
                      <div class="text-xs text-gray-500">{{ inq.category }} — {{ inq.created_at?.slice(0, 10) }}</div>
                    </div>
                    <span
                      :class="[
                        'text-xs px-2 py-1 rounded-full',
                        inq.status === 'open'
                          ? 'bg-red-50 text-red-600'
                          : 'bg-amber-50 text-amber-600'
                      ]"
                    >{{ inq.status === 'open' ? '未対応' : '対応中' }}</span>
                  </li>
                </ul>
                <p class="border-t bg-gray-50 px-5 py-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" label="問い合わせ" :href="route('inquiries.index')" /></p>
              </div>

            </div>
          </div>
        </section>

        <section v-if="canManage" id="office-work" aria-labelledby="office-heading" class="workspace-role-area workspace-role-office scroll-mt-4">
          <div class="workspace-role-header"><h3 id="office-heading" class="workspace-section-title">事務の確認事項</h3><span class="text-sm">請求・集金</span></div>
          <div class="space-y-3 p-4">
            <!-- 前月分 請求業務の状態 -->
          <div class="rounded-lg border px-4 py-3" :class="isBillingWindow && billingPeriod?.status !== 'completed' ? 'bg-amber-50 border-amber-200' : 'bg-white border-gray-200'">
            <div class="flex flex-wrap items-center gap-3 text-sm">
              <span class="font-semibold text-gray-700">{{ billingMonth }}分の国保連請求</span>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', billingGuide.cls]">{{ billingGuide.label }}</span>
              <span class="text-gray-600">{{ billingGuide.text }}</span>
            </div>
            <p class="mt-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" label="月次請求" :href="route('billing.index', { month: billingMonth })" /></p>
          </div>
          <div v-for="task in officeTasks" :key="task.key" class="rounded-lg border bg-white px-4 py-3">
            <h4 class="workspace-section-title flex items-center gap-3 font-semibold text-gray-800">{{ task.label }}<span class="rounded-full bg-amber-50 px-2 py-1 text-sm text-amber-900">{{ task.rows.length }}件</span></h4>
            <p v-if="!task.rows.length" class="mt-2 text-sm text-gray-600">該当する項目はありません。</p>
            <ul v-else class="mt-2 divide-y">
              <li v-for="row in task.rows" :key="row.id" class="flex flex-wrap justify-between gap-2 py-2 text-sm"><span>{{ row.child_name }} ／ {{ row.month }}</span><span>{{ row.status }}</span></li>
            </ul>
            <p class="mt-2 text-xs text-gray-600"><MenuDestination prefix="対応先：メニュー" :label="task.menu" :href="route(officeRoutes[task.key])" /></p>
          </div>
          </div>
        </section>


      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
