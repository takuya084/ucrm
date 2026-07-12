<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import QuickNav from '@/Components/QuickNav.vue'

const props = defineProps({
  todayStats:            Object,
  activeChildren:        Number,
  expiringCertificates:  Array,
  monitoringDue:         Array,
  pendingAgreements:     Array,
  openInquiries:         Array,
  billingMonth:          String,
  billingPeriod:         Object,
})

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
  <Head title="ダッシュボード" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">ダッシュボード</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <QuickNav />

        <!-- 前月分 請求業務の状態 -->
        <Link :href="billingPeriod ? route('billing.show', billingPeriod.id) : route('billing.index')"
          class="block rounded-lg border px-4 py-3 transition hover:shadow"
          :class="billingPeriod?.status === 'completed' ? 'bg-white border-gray-200' : isBillingWindow ? 'bg-amber-50 border-amber-200' : 'bg-white border-gray-200'">
          <div class="flex items-center gap-3 flex-wrap text-sm">
            <span class="font-semibold text-gray-700">{{ billingMonth }}分の国保連請求</span>
            <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', billingGuide.cls]">{{ billingGuide.label }}</span>
            <span class="text-gray-600">{{ billingGuide.text }}</span>
            <span class="ml-auto text-xs text-indigo-600">開く →</span>
          </div>
          <p v-if="isBillingWindow && billingPeriod?.status !== 'completed'" class="text-xs text-amber-700 mt-1">
            毎月10日が国保連への請求締切です。
          </p>
        </Link>

        <!-- 今日のサマリー -->
        <section>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
            本日の状況（{{ todayStats.date }}）
          </h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-5 text-center">
              <div class="text-3xl font-bold text-gray-800">{{ activeChildren }}</div>
              <div class="text-xs text-gray-500 mt-1">契約中の児童</div>
            </div>
            <div class="bg-green-50 rounded-lg shadow-sm p-5 text-center">
              <div class="text-3xl font-bold text-green-700">{{ todayStats.attended }}</div>
              <div class="text-xs text-gray-500 mt-1">本日出席</div>
            </div>
            <div class="bg-yellow-50 rounded-lg shadow-sm p-5 text-center">
              <div class="text-3xl font-bold text-yellow-700">{{ todayStats.total - todayStats.attended }}</div>
              <div class="text-xs text-gray-500 mt-1">本日欠席</div>
            </div>
            <div class="bg-indigo-50 rounded-lg shadow-sm p-5 text-center">
              <div class="text-3xl font-bold text-indigo-700">{{ todayStats.withSupport }}</div>
              <div class="text-xs text-gray-500 mt-1">支援記録済</div>
            </div>
          </div>
        </section>

        <!-- アラートセクション -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- 受給者証 期限アラート -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700">
                受給者証 期限アラート
                <span v-if="expiringCertificates.length" class="ml-2 px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">
                  {{ expiringCertificates.length }}件
                </span>
              </h3>
            </div>
            <div v-if="expiringCertificates.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
              期限切れ間近の受給者証はありません
            </div>
            <ul v-else class="divide-y">
              <li v-for="cert in expiringCertificates" :key="cert.id" class="px-5 py-3 flex items-center justify-between">
                <div>
                  <Link :href="route('children.show', cert.child_id)" class="text-sm font-medium text-gray-800 hover:text-indigo-600">
                    {{ cert.child?.name }}
                  </Link>
                  <div class="text-xs text-gray-500">有効期限：{{ cert.valid_to }}</div>
                </div>
                <span
                  :class="[
                    'text-xs font-medium px-2 py-1 rounded-full',
                    daysUntil(cert.valid_to) <= 7
                      ? 'bg-red-100 text-red-700'
                      : 'bg-yellow-100 text-yellow-700'
                  ]"
                >
                  あと {{ daysUntil(cert.valid_to) }}日
                </span>
              </li>
            </ul>
          </div>

          <!-- モニタリング期限アラート -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700">
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
                  <Link :href="route('children.show', mon.child_id)" class="text-sm font-medium text-gray-800 hover:text-indigo-600">
                    {{ mon.child?.name }}
                  </Link>
                  <div class="text-xs text-gray-500">次回予定：{{ mon.next_review_date }}</div>
                </div>
                <span
                  :class="[
                    'text-xs font-medium px-2 py-1 rounded-full',
                    daysUntil(mon.next_review_date) < 0
                      ? 'bg-red-100 text-red-700'
                      : daysUntil(mon.next_review_date) <= 7
                        ? 'bg-orange-100 text-orange-700'
                        : 'bg-yellow-100 text-yellow-700'
                  ]"
                >
                  {{ daysUntil(mon.next_review_date) < 0 ? `${Math.abs(daysUntil(mon.next_review_date))}日超過` : `あと${daysUntil(mon.next_review_date)}日` }}
                </span>
              </li>
            </ul>
          </div>

          <!-- 個別支援計画 同意待ち -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b">
              <h3 class="text-sm font-semibold text-gray-700">
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
                  <Link :href="route('children.show', plan.child_id)" class="text-sm font-medium text-gray-800 hover:text-indigo-600">
                    {{ plan.child?.name }}
                  </Link>
                  <div class="text-xs text-gray-500">{{ plan.valid_from }} 〜 {{ plan.valid_to }}</div>
                </div>
                <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-full">同意待ち</span>
              </li>
            </ul>
          </div>

          <!-- 未対応・対応中の問い合わせ -->
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b">
              <h3 class="text-sm font-semibold text-gray-700">
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
                  <Link :href="route('children.show', inq.child_id)" class="text-sm font-medium text-gray-800 hover:text-indigo-600">
                    {{ inq.child?.name }}
                  </Link>
                  <div class="text-xs text-gray-500">{{ inq.category }} — {{ inq.created_at?.slice(0, 10) }}</div>
                </div>
                <span
                  :class="[
                    'text-xs px-2 py-1 rounded-full',
                    inq.status === 'open'
                      ? 'bg-purple-50 text-purple-600'
                      : 'bg-yellow-50 text-yellow-600'
                  ]"
                >{{ inq.status === 'open' ? '未対応' : '対応中' }}</span>
              </li>
            </ul>
          </div>

        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
