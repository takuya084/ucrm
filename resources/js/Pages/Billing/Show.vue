<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  period: Object,
  exports: { type: Array, default: () => [] },
  kpi:    { type: Object, default: () => ({}) },
  trend:  { type: Array,  default: () => [] },
})

const pct = (v) => v == null ? '—' : `${v}%`
const pctColor = (v, good = 80, warn = 65) => {
  if (v == null) return 'text-gray-400'
  return v >= good ? 'text-emerald-600' : v >= warn ? 'text-amber-600' : 'text-red-600'
}
const rateColorInverse = (v, good = 5, warn = 10) => {
  if (v == null) return 'text-gray-400'
  return v <= good ? 'text-emerald-600' : v <= warn ? 'text-amber-600' : 'text-red-600'
}

// 6ヶ月スパークライン（SVG polyline）
const spark = computed(() => {
  const vals = props.trend.map(t => t.total)
  const max = Math.max(...vals, 1)
  const w = 240, h = 44
  const step = vals.length > 1 ? w / (vals.length - 1) : 0
  const points = vals.map((v, i) => `${(i * step).toFixed(1)},${(h - (v / max) * (h - 4) - 2).toFixed(1)}`).join(' ')
  const bars = vals.map((v, i) => ({
    x: i * step,
    y: h - (v / max) * (h - 4) - 2,
    v,
    ym: props.trend[i]?.year_month,
  }))
  return { points, bars, w, h, max }
})

const warnings = ref(null)
const checking = ref(false)
const showOutputMenu = ref(false)
const showProblemList = ref(false)

// 児童別バッジから自動集計（追加の API コールなし）
const reviewRollup = computed(() => {
  let errors = 0, warnings = 0, ok = 0
  const errorRows = [], warningRows = []
  for (const d of props.period?.billing_details || []) {
    if (d.review_level === 'error') { errors++; errorRows.push(d) }
    else if (d.review_level === 'warning') { warnings++; warningRows.push(d) }
    else ok++
  }
  return { errors, warnings, ok, errorRows, warningRows }
})

const runValidate = async () => {
  checking.value = true
  try {
    const { data } = await axios.get(route('billing.validate-export', props.period.id))
    warnings.value = data.warnings || []
  } finally {
    checking.value = false
  }
}

const exportBundle = () => {
  if (warnings.value && warnings.value.some(w => w.level === 'error')) {
    if (!confirm('エラーが残っていますが、このまま出力しますか？')) return
  }
  window.location = route('billing.export-bundle', props.period.id)
}

const markSubmitted = (exportId) => {
  if (!confirm('国保連に送信済としてマークしますか？')) return
  router.post(route('billing.exports.mark-submitted', exportId))
}

const KIND_LABEL = {
  bundle: '複式（ZIP）', billing: '請求明細', performance: '実績記録票', cap_mgmt: '上限管理',
}
const fmtDate = (s) => s ? new Date(s).toLocaleString('ja-JP') : '-'
const fmtSize = (n) => n < 1024 ? `${n}B` : n < 1024*1024 ? `${(n/1024).toFixed(1)}KB` : `${(n/1024/1024).toFixed(2)}MB`

const STATUS_LABEL = {
  draft: '下書き', calculating: '計算中', confirmed: '確定済',
  submitted: '提出済', completed: '完了', error: 'エラー',
}

const confirmPeriod = () => {
  if (!confirm('この請求を確定しますか？確定後は自動再計算できなくなります。')) return
  router.patch(route('billing.confirm', props.period.id))
}

// ── 国保連支払通知との突合 ─────────────────
const paymentForm = ref({
  payment_decided_amount:  props.period.payment_decided_amount ?? null,
  payment_decided_at:      props.period.payment_decided_at?.slice(0, 10) ?? '',
  payment_difference_note: props.period.payment_difference_note ?? '',
})

const paymentDiff = computed(() => {
  if (paymentForm.value.payment_decided_amount == null || paymentForm.value.payment_decided_amount === '') return null
  return Number(paymentForm.value.payment_decided_amount) - Number(props.kpi.insurance_amount ?? 0)
})

const savePaymentDecision = () => {
  router.patch(route('billing.payment-decision', props.period.id), paymentForm.value, { preserveScroll: true })
}

const fmt = (n) => Number(n).toLocaleString()

const REVIEW_BADGE = {
  ok:      { cls: 'bg-emerald-100 text-emerald-700', label: '✓ OK' },
  warning: { cls: 'bg-amber-100 text-amber-700',    label: '⚠ 要確認' },
  error:   { cls: 'bg-red-100 text-red-700',        label: '✕ 要修正' },
}

const CAP_STATUS_LABEL = {
  missing:   { cls: 'bg-red-100 text-red-700',        label: '未作成' },
  draft:     { cls: 'bg-gray-100 text-gray-600',      label: '下書き' },
  created:   { cls: 'bg-blue-100 text-blue-700',      label: '作成済' },
  sent:      { cls: 'bg-amber-100 text-amber-700',    label: '送付済' },
  received:  { cls: 'bg-purple-100 text-purple-700',  label: '受領済' },
  confirmed: { cls: 'bg-green-100 text-green-700',    label: '確定済' },
}

// ── 請求業務ステップガイド ─────────────────
const flowSteps = computed(() => {
  const s = props.period.status
  const confirmed = ['confirmed', 'submitted', 'completed'].includes(s)
  const submitted = ['submitted', 'completed'].includes(s)
  return [
    { label: '請求計算',     done: true },
    { label: '内容確認',     done: confirmed || reviewRollup.value.errors === 0 },
    { label: '確定',         done: confirmed },
    { label: 'CSV出力',      done: confirmed && props.exports.length > 0 },
    { label: '国保連へ伝送', done: submitted },
    { label: '支払突合',     done: s === 'completed' },
  ]
})
const currentStep = computed(() => flowSteps.value.findIndex(st => !st.done))
const NEXT_ACTIONS = {
  1: '児童別明細の「✕ 要修正」を解消してください（各行の「詳細」から確認できます）。',
  2: '内容に問題がなければ、右上の「確定する」を押してください（確定後は再計算できなくなります）。',
  3: '右上の「複式CSV一括出力（ZIP）」を押して、提出用ファイルをダウンロードしてください。',
  4: 'ダウンロードしたCSVを国保連の電子請求受付システム（取込送信）でアップロードし、送信できたら下の出力履歴で「送信済マーク」を押してください。',
  5: '国保連から支払決定通知が届いたら、下の「支払決定通知との突合」に通知記載の金額を入力してください。',
}
</script>

<template>
  <Head :title="`請求詳細 ${period.year_month}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <h2 class="font-semibold text-xl text-gray-800">{{ period.year_month }} 請求詳細</h2>
        <div class="flex items-center gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">
            戻る
          </Link>
          <button v-if="period.status === 'draft'" @click="confirmPeriod"
            class="px-4 py-1.5 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            確定する
          </button>
          <template v-if="['confirmed','submitted','completed'].includes(period.status)">
            <button @click="exportBundle"
              class="px-4 py-1.5 text-sm bg-emerald-600 text-white rounded hover:bg-emerald-700 transition font-medium">
              複式CSV一括出力（ZIP）
            </button>
            <div class="relative">
              <button @click="showOutputMenu = !showOutputMenu"
                class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 transition inline-flex items-center gap-1">
                別途出力
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
              </button>
              <div v-if="showOutputMenu" class="fixed inset-0 z-40" @click="showOutputMenu = false" />
              <div v-if="showOutputMenu"
                class="absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50 py-1">
                <div class="px-3 py-1.5 text-[10px] text-gray-400 uppercase tracking-wider">CSV（部分出力）</div>
                <a :href="route('billing.export', period.id)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  請求明細のみ（CSV）
                </a>
                <a :href="route('billing.export-performance', period.id)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  実績記録のみ（CSV）
                </a>
                <div class="border-t my-1" />
                <div class="px-3 py-1.5 text-[10px] text-gray-400 uppercase tracking-wider">PDF</div>
                <a :href="route('billing.performance-pdf-bundle', period.id)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  実績記録票PDF（全員ZIP）
                </a>
                <a :href="route('billing.cap-management.payment-list', { year_month: period.year_month })"
                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  利用者負担額一覧表PDF
                </a>
                <a :href="route('billing.proxy-receipt-bundle', period.id)"
                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  代理受領額通知書PDF（全員ZIP）
                </a>
                <div class="border-t my-1" />
                <button @click="runValidate(); showOutputMenu = false" :disabled="checking"
                  class="block w-full text-left px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 disabled:opacity-50">
                  {{ checking ? 'チェック中...' : '出力前 整合性チェック' }}
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 請求業務ステップガイド -->
        <div class="bg-white shadow-sm rounded-lg p-4">
          <ol class="flex flex-wrap items-center gap-y-2">
            <li v-for="(step, i) in flowSteps" :key="step.label" class="flex items-center">
              <span :class="[
                  'flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap',
                  step.done ? 'bg-emerald-50 text-emerald-700'
                    : i === currentStep ? 'bg-indigo-600 text-white shadow'
                      : 'bg-gray-100 text-gray-400',
                ]">
                <span :class="[
                    'inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] font-bold',
                    step.done ? 'bg-emerald-500 text-white' : i === currentStep ? 'bg-white text-indigo-600' : 'bg-gray-300 text-white',
                  ]">
                  <template v-if="step.done">✓</template>
                  <template v-else>{{ i + 1 }}</template>
                </span>
                {{ step.label }}
              </span>
              <svg v-if="i < flowSteps.length - 1" class="w-4 h-4 text-gray-300 mx-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
            </li>
          </ol>
          <p v-if="currentStep !== -1 && NEXT_ACTIONS[currentStep]" class="mt-3 text-sm text-indigo-800 bg-indigo-50 rounded px-3 py-2">
            <span class="font-semibold">次にやること：</span>{{ NEXT_ACTIONS[currentStep] }}
          </p>
          <p v-else-if="currentStep === -1" class="mt-3 text-sm text-emerald-700 bg-emerald-50 rounded px-3 py-2">
            この月の請求業務はすべて完了しています。
          </p>
        </div>

        <!-- サマリ（基本情報＋確認状態ロールアップ） -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- 左: 基本情報 -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
              <div>
                <div class="text-xs text-gray-500">ステータス</div>
                <div class="text-lg font-bold">{{ STATUS_LABEL[period.status] }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">対象児童数</div>
                <div class="text-lg font-bold">{{ kpi.children_count ?? 0 }}名</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">費用合計</div>
                <div class="text-lg font-bold">{{ fmt(kpi.total_amount ?? 0) }}円</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">利用者負担合計</div>
                <div class="text-lg font-bold">{{ fmt(kpi.copayment_applied ?? 0) }}円</div>
              </div>
            </div>

            <!-- 右: 確認状態ロールアップ -->
            <div class="border-l lg:pl-5 flex items-center">
              <div class="w-full">
                <div class="flex items-center justify-between mb-2">
                  <div class="text-xs text-gray-500">月初確認状態</div>
                  <button v-if="reviewRollup.errors + reviewRollup.warnings > 0"
                    @click="showProblemList = !showProblemList"
                    class="text-xs text-indigo-600 hover:underline">
                    {{ showProblemList ? '閉じる' : '該当児童を表示 →' }}
                  </button>
                </div>
                <div class="flex items-center gap-3">
                  <div class="flex-1 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-100 text-emerald-700 text-sm font-semibold">
                      ✓ OK {{ reviewRollup.ok }}名
                    </span>
                    <span v-if="reviewRollup.warnings > 0"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-amber-100 text-amber-700 text-sm font-semibold">
                      ⚠ 要確認 {{ reviewRollup.warnings }}名
                    </span>
                    <span v-if="reviewRollup.errors > 0"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-red-100 text-red-700 text-sm font-semibold">
                      ✕ 要修正 {{ reviewRollup.errors }}名
                    </span>
                    <span v-if="reviewRollup.errors + reviewRollup.warnings === 0"
                      class="text-xs text-emerald-600">問題は検出されていません。</span>
                  </div>
                </div>

                <!-- 問題児童リスト（展開時のみ） -->
                <div v-if="showProblemList" class="mt-3 border rounded divide-y text-xs max-h-48 overflow-y-auto">
                  <div v-for="d in [...reviewRollup.errorRows, ...reviewRollup.warningRows]" :key="d.id"
                    class="px-3 py-1.5 flex items-center justify-between hover:bg-gray-50">
                    <div class="flex items-center gap-2 min-w-0">
                      <span :class="['px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0', REVIEW_BADGE[d.review_level].cls]">
                        {{ REVIEW_BADGE[d.review_level].label }}
                      </span>
                      <span class="font-medium truncate">{{ d.child?.name }}</span>
                      <span class="text-gray-500 truncate">{{ (d.review_issues || []).map(i => i.message).join(' / ') }}</span>
                    </div>
                    <Link :href="route('billing.details.show', d.id)" class="text-indigo-600 hover:underline shrink-0 ml-2">詳細</Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 国保連支払通知との突合（提出後に表示） -->
        <div v-if="['submitted','completed'].includes(period.status)" class="bg-white shadow-sm rounded-lg p-5">
          <div class="flex items-center gap-3 mb-3 flex-wrap">
            <h3 class="text-sm font-semibold text-gray-700">国保連 支払決定通知との突合</h3>
            <span v-if="period.payment_decided_amount != null && paymentDiff === 0"
              class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">請求額と一致</span>
            <span v-else-if="period.payment_decided_amount != null && paymentDiff !== null && paymentDiff !== 0"
              class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">
              差異 {{ fmt(Math.abs(paymentDiff)) }}円（{{ paymentDiff > 0 ? '支払超過' : '支払不足' }}）
            </span>
            <span v-else class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">未記録</span>
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">請求給付費（当システム）</label>
              <div class="text-sm font-bold py-1.5">{{ fmt(kpi.insurance_amount ?? 0) }}円</div>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">支払決定額（通知記載額）</label>
              <input v-model.number="paymentForm.payment_decided_amount" type="number" min="0"
                class="border border-gray-300 rounded px-3 py-1.5 text-sm w-36 text-right" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">支払決定日</label>
              <input v-model="paymentForm.payment_decided_at" type="date"
                class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div class="flex-1 min-w-[14rem]">
              <label class="block text-xs text-gray-500 mb-1">差異メモ（差異がある場合は必須）</label>
              <input v-model="paymentForm.payment_difference_note" type="text"
                class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                placeholder="例：○○さん分が受給者証番号誤りで返戻" />
            </div>
            <button @click="savePaymentDecision"
              class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">
              記録する
            </button>
          </div>
          <p v-if="paymentDiff !== null && paymentDiff !== 0" class="mt-2 text-xs text-red-600">
            差異があります。<Link :href="route('billing.returns.index')" class="underline">返戻管理</Link>・
            <Link :href="route('billing.error-claims.index')" class="underline">過誤申立</Link>を確認してください。
          </p>
          <p v-else-if="period.status === 'submitted'" class="mt-2 text-xs text-gray-400">
            支払決定額が請求額と一致した場合、この月の請求は自動的に「完了」になります。
          </p>
        </div>

        <!-- 事業所KPI -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">事業所KPI（{{ period.year_month }}）</h3>
            <span class="text-[10px] text-gray-400">定員 {{ kpi.capacity_per_day ?? '—' }}名 × 営業 {{ kpi.business_days ?? '—' }}日</span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">利用率</div>
              <div :class="['text-2xl font-bold', pctColor(kpi.utilization_rate)]">{{ pct(kpi.utilization_rate) }}</div>
              <div class="text-[10px] text-gray-400 mt-1">出席 {{ kpi.attended ?? 0 }}日 / 提供可 {{ (kpi.capacity_per_day ?? 0) * (kpi.business_days ?? 0) }}日</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">キャンセル率</div>
              <div :class="['text-2xl font-bold', rateColorInverse(kpi.cancellation_rate)]">{{ pct(kpi.cancellation_rate) }}</div>
              <div class="text-[10px] text-gray-400 mt-1">欠席 {{ kpi.absent ?? 0 }}日 / 予定 {{ kpi.scheduled ?? 0 }}日</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">送迎実施率</div>
              <div class="text-2xl font-bold text-gray-700">{{ pct(kpi.pickup_rate) }}</div>
              <div class="text-[10px] text-gray-400 mt-1">迎 {{ kpi.pickups ?? 0 }} / 送 {{ kpi.dropoffs ?? 0 }}</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">給付費</div>
              <div class="text-xl font-bold text-gray-700">{{ fmt(kpi.insurance_amount ?? 0) }}円</div>
              <div class="text-[10px] text-gray-400 mt-1">請求対象額</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">売上推移（6ヶ月）</div>
              <svg :width="spark.w" :height="spark.h" class="mt-1">
                <polyline :points="spark.points" fill="none" stroke="#6366f1" stroke-width="1.5" />
                <circle v-for="(b, i) in spark.bars" :key="i" :cx="b.x" :cy="b.y" r="2.5"
                  :fill="b.ym === period.year_month ? '#ef4444' : '#6366f1'">
                  <title>{{ b.ym }}: {{ fmt(b.v) }}円</title>
                </circle>
              </svg>
              <div class="text-[10px] text-gray-400 flex justify-between mt-0.5">
                <span>{{ trend[0]?.year_month }}</span>
                <span>{{ trend[trend.length - 1]?.year_month }}</span>
              </div>
            </div>
          </div>

          <!-- 損益サマリ（経営情報のため管理者のみ） -->
          <div v-if="$page.props.auth.staff_role === 'admin'"
            class="mt-4 pt-3 border-t grid grid-cols-2 sm:grid-cols-5 gap-4">
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">売上（請求＋自己負担）</div>
              <div class="text-xl font-bold text-gray-800">{{ fmt(kpi.revenue ?? 0) }}円</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">人件費</div>
              <div class="text-xl font-bold text-gray-700">{{ fmt(kpi.labor_cost ?? 0) }}円</div>
              <div class="text-[10px] text-gray-400 mt-1"
                :title="`常勤 ${fmt(kpi.labor_breakdown?.full_time ?? 0)}円 / パート ${fmt(kpi.labor_breakdown?.part_time ?? 0)}円`">
                常勤{{ fmt(kpi.labor_breakdown?.full_time ?? 0) }} / ﾊﾟｰﾄ{{ fmt(kpi.labor_breakdown?.part_time ?? 0) }}
              </div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">人件費率</div>
              <div :class="['text-2xl font-bold', rateColorInverse(kpi.labor_ratio, 60, 75)]">{{ pct(kpi.labor_ratio) }}</div>
              <div class="text-[10px] text-gray-400 mt-1">目安 60〜75%</div>
            </div>
            <div class="border rounded-lg p-3">
              <div class="text-[10px] text-gray-500">経費</div>
              <div class="text-xl font-bold text-gray-700">{{ fmt(kpi.expenses ?? 0) }}円</div>
              <div class="text-[10px] text-gray-400 mt-1">
                <Link :href="route('billing.expenses.index', { month: period.year_month })" class="text-indigo-500 hover:underline">
                  編集 →
                </Link>
              </div>
            </div>
            <div class="border rounded-lg p-3"
              :class="kpi.net_profit < 0 ? 'bg-red-50 border-red-200' : kpi.net_profit > 0 ? 'bg-emerald-50 border-emerald-200' : ''">
              <div class="text-[10px] text-gray-500">営業利益</div>
              <div :class="['text-2xl font-bold', (kpi.net_profit ?? 0) < 0 ? 'text-red-600' : 'text-emerald-700']">
                {{ fmt(kpi.net_profit ?? 0) }}円
              </div>
              <div class="text-[10px] text-gray-400 mt-1">利益率 {{ pct(kpi.profit_ratio) }}</div>
            </div>
          </div>

          <!-- 加算取得サマリ -->
          <div v-if="kpi.addition_summary?.length" class="mt-4 pt-3 border-t">
            <div class="text-xs text-gray-500 mb-2">加算取得サマリ</div>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="a in kpi.addition_summary" :key="a.service_name"
                class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] px-2 py-0.5 rounded">
                {{ a.service_name }}
                <span class="text-emerald-600 font-semibold">{{ a.total_count }}回</span>
                <span class="text-emerald-500">/{{ a.child_count }}名</span>
              </span>
            </div>
          </div>
        </div>

        <!-- 出力前整合性チェック結果（CSV/エクスポート系の検証） -->
        <div v-if="warnings !== null" class="bg-white shadow-sm rounded-lg p-5 border-l-4 border-amber-400">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">出力前 整合性チェック結果</h3>
            <button @click="warnings = null" class="text-xs text-gray-400 hover:text-gray-600">閉じる</button>
          </div>
          <div v-if="warnings.length === 0" class="text-sm text-emerald-600">問題は検出されませんでした。</div>
          <ul v-else class="space-y-1 text-sm">
            <li v-for="(w, i) in warnings" :key="i" class="flex items-start gap-2">
              <span :class="w.level === 'error' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'"
                class="px-2 py-0.5 rounded text-xs shrink-0">
                {{ w.level === 'error' ? 'エラー' : '警告' }}
              </span>
              <span>{{ w.message }}</span>
            </li>
          </ul>
        </div>

        <!-- 出力履歴 -->
        <div v-if="exports.length > 0" class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">出力履歴</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                  <th class="px-4 py-2 text-left">種別</th>
                  <th class="px-4 py-2 text-left">ファイル</th>
                  <th class="px-4 py-2 text-right">サイズ</th>
                  <th class="px-4 py-2 text-left">出力日時</th>
                  <th class="px-4 py-2 text-left">出力者</th>
                  <th class="px-4 py-2 text-left">送信状況</th>
                  <th class="px-4 py-2 text-right">操作</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="ex in exports" :key="ex.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">{{ KIND_LABEL[ex.kind] || ex.kind }}</td>
                  <td class="px-4 py-3 font-mono text-xs">{{ ex.file_name }}</td>
                  <td class="px-4 py-3 text-right text-xs">{{ fmtSize(ex.file_size) }}</td>
                  <td class="px-4 py-3 text-xs">{{ fmtDate(ex.created_at) }}</td>
                  <td class="px-4 py-3 text-xs">{{ ex.created_by?.name || '-' }}</td>
                  <td class="px-4 py-3 text-xs">
                    <span v-if="ex.is_submitted" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                      送信済 {{ fmtDate(ex.submitted_at) }}
                    </span>
                    <span v-else class="text-gray-400">未送信</span>
                  </td>
                  <td class="px-4 py-3 text-right space-x-2 text-xs">
                    <a :href="route('billing.exports.download', ex.id)" class="text-indigo-600 hover:underline">再DL</a>
                    <button v-if="!ex.is_submitted" @click="markSubmitted(ex.id)" class="text-blue-600 hover:underline">送信済マーク</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 児童別明細テーブル -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">児童別請求明細</h3>
            <span class="text-[10px] text-gray-400">月初確認用サマリ付き</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                  <th class="px-3 py-2 text-center">確認</th>
                  <th class="px-3 py-2 text-left">児童名</th>
                  <th class="px-3 py-2 text-left">種別</th>
                  <th class="px-3 py-2 text-right">利用日数</th>
                  <th class="px-3 py-2 text-right">欠席</th>
                  <th class="px-3 py-2 text-center">送迎 実績/請求</th>
                  <th class="px-3 py-2 text-center">加算</th>
                  <th class="px-3 py-2 text-center">上限管理</th>
                  <th class="px-3 py-2 text-right">費用合計</th>
                  <th class="px-3 py-2 text-right">利用者負担</th>
                  <th class="px-3 py-2 text-right">操作</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="detail in period.billing_details" :key="detail.id"
                    :class="[
                      'hover:bg-gray-50',
                      detail.review_level === 'error' ? 'bg-red-50/40' : detail.review_level === 'warning' ? 'bg-amber-50/40' : ''
                    ]">
                  <td class="px-3 py-3 text-center">
                    <span
                      :class="['text-[10px] font-semibold px-2 py-0.5 rounded whitespace-nowrap cursor-help', REVIEW_BADGE[detail.review_level || 'ok'].cls]"
                      :title="(detail.review_issues || []).map(i => (i.level==='error'?'✕':'⚠')+' '+i.message).join('\n') || '問題なし'">
                      {{ REVIEW_BADGE[detail.review_level || 'ok'].label }}
                      <span v-if="detail.review_issues?.length" class="ml-1">({{ detail.review_issues.length }})</span>
                    </span>
                  </td>
                  <td class="px-3 py-3 font-medium">{{ detail.child?.name }}</td>
                  <td class="px-3 py-3 text-xs">
                    <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                      {{ detail.service_type === 'houday' ? '放デイ' : '児発' }}
                    </span>
                  </td>
                  <td class="px-3 py-3 text-right">
                    <span
                      :class="detail.recipient_certificate?.monthly_limit && detail.total_days > detail.recipient_certificate.monthly_limit
                        ? 'text-red-600 font-bold'
                        : detail.recipient_certificate?.monthly_limit && detail.total_days === detail.recipient_certificate.monthly_limit
                          ? 'text-amber-600 font-semibold'
                          : ''"
                      :title="detail.recipient_certificate?.monthly_limit ? `支給量 ${detail.recipient_certificate.monthly_limit}日` : ''">
                      {{ detail.total_days }}日
                      <span v-if="detail.recipient_certificate?.monthly_limit" class="text-xs text-gray-400">/ {{ detail.recipient_certificate.monthly_limit }}</span>
                    </span>
                  </td>
                  <td class="px-3 py-3 text-right text-xs">
                    <span :class="detail.absent_days > 0 ? 'text-orange-600 font-medium' : 'text-gray-400'">
                      {{ detail.absent_days ?? 0 }}日
                    </span>
                  </td>
                  <td class="px-3 py-3 text-center text-xs">
                    <div v-if="(detail.pickup_count ?? 0) + (detail.dropoff_count ?? 0) + (detail.pickup_billed ?? 0) + (detail.dropoff_billed ?? 0) > 0"
                         class="leading-tight">
                      <div :class="detail.pickup_count !== detail.pickup_billed ? 'text-red-600 font-semibold' : 'text-gray-700'">
                        迎 {{ detail.pickup_count ?? 0 }}/{{ detail.pickup_billed ?? 0 }}
                      </div>
                      <div :class="detail.dropoff_count !== detail.dropoff_billed ? 'text-red-600 font-semibold' : 'text-gray-700'">
                        送 {{ detail.dropoff_count ?? 0 }}/{{ detail.dropoff_billed ?? 0 }}
                      </div>
                    </div>
                    <span v-else class="text-gray-300">—</span>
                  </td>
                  <td class="px-3 py-3 text-center text-xs">
                    <span
                      v-if="detail.addition_count > 0"
                      class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded cursor-help"
                      :title="detail.addition_lines?.map(a => `${a.service_name}×${a.count}`).join('\n')">
                      {{ detail.addition_count }}種
                    </span>
                    <span v-else class="text-gray-300">—</span>
                  </td>
                  <td class="px-3 py-3 text-center text-xs">
                    <span v-if="detail.is_cap_management_target && detail.cap_management_status"
                      :class="['px-2 py-0.5 rounded', CAP_STATUS_LABEL[detail.cap_management_status]?.cls || 'bg-gray-100 text-gray-600']">
                      {{ CAP_STATUS_LABEL[detail.cap_management_status]?.label || detail.cap_management_status }}
                    </span>
                    <span v-else-if="detail.is_cap_management_target" class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded">対象</span>
                    <span v-else class="text-gray-300">—</span>
                  </td>
                  <td class="px-3 py-3 text-right">{{ fmt(detail.total_amount) }}円</td>
                  <td class="px-3 py-3 text-right font-semibold">{{ fmt(detail.copayment_cap_applied) }}円</td>
                  <td class="px-3 py-3 text-right space-x-2 whitespace-nowrap">
                    <Link :href="route('billing.details.show', detail.id)" class="text-indigo-600 hover:underline text-xs">詳細</Link>
                    <Link v-if="period.status === 'draft'" :href="route('billing.details.edit', detail.id)" class="text-gray-500 hover:underline text-xs">編集</Link>
                    <a :href="route('billing.details.performance-pdf', detail.id)" class="text-rose-600 hover:underline text-xs">実績票PDF</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
