<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  period: Object,
  exports: { type: Array, default: () => [] },
})

const warnings = ref(null)
const checking = ref(false)

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
  Inertia.post(route('billing.exports.mark-submitted', exportId))
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
  Inertia.patch(route('billing.confirm', props.period.id))
}

const fmt = (n) => Number(n).toLocaleString()
</script>

<template>
  <Head :title="`請求詳細 ${period.year_month}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ period.year_month }} 請求詳細</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">
            戻る
          </Link>
          <button v-if="period.status === 'draft'" @click="confirmPeriod"
            class="px-4 py-1.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            確定する
          </button>
          <template v-if="['confirmed','submitted','completed'].includes(period.status)">
            <button @click="runValidate" :disabled="checking"
              class="px-3 py-1.5 text-xs border border-amber-400 text-amber-700 rounded hover:bg-amber-50 transition disabled:opacity-50">
              {{ checking ? 'チェック中...' : '事前チェック' }}
            </button>
            <button @click="exportBundle"
              class="px-4 py-1.5 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700 transition">
              複式CSV一括出力（ZIP）
            </button>
            <a :href="route('billing.export', period.id)"
              class="px-3 py-1.5 text-xs border border-green-500 text-green-600 rounded hover:bg-green-50 transition">
              請求のみ
            </a>
            <a :href="route('billing.export-performance', period.id)"
              class="px-3 py-1.5 text-xs border border-green-500 text-green-600 rounded hover:bg-green-50 transition">
              実績のみ
            </a>
          </template>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- サマリ -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div>
              <div class="text-xs text-gray-500">ステータス</div>
              <div class="text-lg font-bold">{{ STATUS_LABEL[period.status] }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">対象児童数</div>
              <div class="text-lg font-bold">{{ period.billing_details?.length ?? 0 }}名</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">費用合計</div>
              <div class="text-lg font-bold">{{ fmt(period.billing_details?.reduce((s, d) => s + d.total_amount, 0) ?? 0) }}円</div>
            </div>
            <div>
              <div class="text-xs text-gray-500">利用者負担合計</div>
              <div class="text-lg font-bold">{{ fmt(period.billing_details?.reduce((s, d) => s + d.copayment_cap_applied, 0) ?? 0) }}円</div>
            </div>
          </div>
        </div>

        <!-- 事前チェック結果 -->
        <div v-if="warnings !== null" class="bg-white shadow-sm rounded-lg p-5">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">事前チェック結果</h3>
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
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">児童別請求明細</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                  <th class="px-4 py-2 text-left">児童名</th>
                  <th class="px-4 py-2 text-left">種別</th>
                  <th class="px-4 py-2 text-right">利用日数</th>
                  <th class="px-4 py-2 text-right">合計単位</th>
                  <th class="px-4 py-2 text-right">費用合計</th>
                  <th class="px-4 py-2 text-right">給付費</th>
                  <th class="px-4 py-2 text-right">利用者負担</th>
                  <th class="px-4 py-2 text-right">操作</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="detail in period.billing_details" :key="detail.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium">{{ detail.child?.name }}</td>
                  <td class="px-4 py-3 text-xs">
                    <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                      {{ detail.service_type === 'houday' ? '放デイ' : '児発' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">{{ detail.total_days }}日</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.total_units) }}</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.total_amount) }}円</td>
                  <td class="px-4 py-3 text-right">{{ fmt(detail.insurance_amount) }}円</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ fmt(detail.copayment_cap_applied) }}円</td>
                  <td class="px-4 py-3 text-right space-x-2">
                    <Link :href="route('billing.details.show', detail.id)" class="text-indigo-600 hover:underline text-xs">詳細</Link>
                    <Link v-if="period.status === 'draft'" :href="route('billing.details.edit', detail.id)" class="text-gray-500 hover:underline text-xs">編集</Link>
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
