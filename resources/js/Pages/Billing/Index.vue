<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  periods:      Object,
  currentMonth: String,
})

const selectedMonth = ref(props.currentMonth)

const STATUS_COLOR = {
  draft:       'bg-gray-100 text-gray-600',
  calculating: 'bg-yellow-100 text-yellow-700',
  confirmed:   'bg-blue-100 text-blue-700',
  submitted:   'bg-green-100 text-green-700',
  completed:   'bg-green-200 text-green-800',
  error:       'bg-red-100 text-red-700',
}

const STATUS_LABEL = {
  draft:       '下書き',
  calculating: '計算中',
  confirmed:   '確定済',
  submitted:   '提出済',
  completed:   '完了',
  error:       'エラー',
}

const calculate = () => {
  const existing = props.periods.data.find(p => p.year_month === selectedMonth.value)
  let message = `${selectedMonth.value} の請求計算を実行しますか？`
  if (existing && existing.status === 'draft') {
    message = `${selectedMonth.value} には計算済みの下書きがあります。\n再計算すると既存の明細（明細編集画面での手動調整を含む）はすべて削除され、出席実績から作り直されます。\n\n実行しますか？`
  }
  if (!confirm(message)) return
  Inertia.post(route('billing.calculate'), { year_month: selectedMonth.value })
}
</script>

<template>
  <Head title="請求管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">請求管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 毎月の流れ -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-sm text-indigo-900">
          <span class="font-semibold">毎月の流れ（1〜10日）：</span>
          ① 前月分の出欠・送迎の入力もれを確認 →
          ② 下で対象年月を選んで「計算実行」 →
          ③ 一覧の「詳細」を開き、内容確認・確定・CSV出力・国保連への伝送まで進める
          <span class="block text-xs text-indigo-600 mt-1">※ 詳細画面に手順ガイドが表示されます。迷ったら「次にやること」に従ってください。</span>
        </div>

        <!-- 操作パネル -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">月次請求計算</h3>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">対象年月</label>
              <input v-model="selectedMonth" type="month" class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <button @click="calculate" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
              計算実行
            </button>
          </div>

          <!-- サブメニュー -->
          <div class="mt-4 pt-4 border-t flex flex-wrap gap-2">
            <Link :href="route('billing.daily-records.index')" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
              実績記録票
            </Link>
            <Link :href="route('billing.cap-management.index')" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
              上限管理
            </Link>
            <Link :href="route('billing.invoices.index')" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
              利用者請求
            </Link>
            <Link :href="route('billing.error-claims.index')" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
              過誤申立
            </Link>
            <Link :href="route('billing.returns.index')" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
              返戻管理
            </Link>
          </div>
        </div>

        <!-- 請求期間一覧 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">請求期間一覧</h3>
          </div>
          <div v-if="periods.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
            まだ請求データがありません。<br />
            上の「対象年月」で請求したい月（通常は前月）を選び、「計算実行」を押してください。
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-5 py-2 text-left">年月</th>
                <th class="px-5 py-2 text-left">ステータス</th>
                <th class="px-5 py-2 text-right">対象児童数</th>
                <th class="px-5 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="period in periods.data" :key="period.id" class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ period.year_month }}</td>
                <td class="px-5 py-3">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[period.status]]">
                    {{ STATUS_LABEL[period.status] }}
                  </span>
                </td>
                <td class="px-5 py-3 text-right">{{ period.billing_details_count }}名</td>
                <td class="px-5 py-3 text-right">
                  <Link :href="route('billing.show', period.id)" class="text-indigo-600 hover:underline text-xs">
                    詳細
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- ページネーション -->
          <div v-if="periods.last_page > 1" class="px-5 py-3 border-t flex gap-2 text-sm">
            <Link
              v-for="link in periods.links"
              :key="link.label"
              :href="link.url ?? '#'"
              v-html="link.label"
              :class="['px-3 py-1 border rounded', link.active ? 'bg-indigo-500 text-white border-indigo-500' : 'border-gray-300 text-gray-600 hover:bg-gray-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
            />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
