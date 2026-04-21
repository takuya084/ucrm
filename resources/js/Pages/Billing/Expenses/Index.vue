<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  yearMonth: String,
  rows:      Array,
  trend:     Array,
})

const selectedMonth = ref(props.yearMonth)
const items = ref(props.rows.map(r => ({ ...r })))

const totalAmount = computed(() => items.value.reduce((s, r) => s + Number(r.amount || 0), 0))

const changeMonth = () => {
  Inertia.get(route('billing.expenses.index'), { month: selectedMonth.value }, { preserveState: false })
}

const save = () => {
  Inertia.post(route('billing.expenses.upsert'), {
    year_month: props.yearMonth,
    items: items.value.map(r => ({
      category: r.category,
      amount:   Number(r.amount || 0),
      note:     r.note || null,
    })),
  }, { preserveScroll: true })
}

const fmt = (n) => Number(n || 0).toLocaleString()

const spark = computed(() => {
  const vals = props.trend.map(t => t.total)
  const max = Math.max(...vals, 1)
  const w = 280, h = 40
  const step = vals.length > 1 ? w / (vals.length - 1) : 0
  const pts = vals.map((v, i) => `${(i*step).toFixed(1)},${(h - (v/max)*(h-4) - 2).toFixed(1)}`).join(' ')
  return { w, h, pts, bars: vals.map((v, i) => ({
    x: i*step, y: h - (v/max)*(h-4) - 2, v, ym: props.trend[i]?.year_month,
  })) }
})
</script>

<template>
  <Head title="経費管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">経費管理</h2>
        <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 月選択 -->
        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">対象年月</label>
            <input v-model="selectedMonth" type="month" @change="changeMonth"
              class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
          </div>
          <div class="ml-auto text-right">
            <div class="text-[10px] text-gray-500">月次経費合計</div>
            <div class="text-xl font-bold">{{ fmt(totalAmount) }}円</div>
          </div>
        </div>

        <!-- カテゴリ別入力 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">{{ yearMonth }} の経費</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left w-40">費目</th>
                <th class="px-4 py-2 text-right w-40">金額</th>
                <th class="px-4 py-2 text-left">備考</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="r in items" :key="r.category">
                <td class="px-4 py-2">{{ r.label }}</td>
                <td class="px-4 py-2">
                  <input v-model.number="r.amount" type="number" min="0" step="1"
                    class="w-full text-right border border-gray-300 rounded px-2 py-1 text-sm" />
                </td>
                <td class="px-4 py-2">
                  <input v-model="r.note" type="text" maxlength="200"
                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    placeholder="任意メモ" />
                </td>
              </tr>
            </tbody>
          </table>
          <div class="px-5 py-3 bg-gray-50 border-t flex justify-end">
            <button @click="save"
              class="px-4 py-1.5 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
              保存
            </button>
          </div>
        </div>

        <!-- 6ヶ月推移 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">経費6ヶ月推移</h3>
          <svg :width="spark.w" :height="spark.h">
            <polyline :points="spark.pts" fill="none" stroke="#f59e0b" stroke-width="1.5" />
            <circle v-for="(b, i) in spark.bars" :key="i" :cx="b.x" :cy="b.y" r="2.5"
              :fill="b.ym === yearMonth ? '#ef4444' : '#f59e0b'">
              <title>{{ b.ym }}: {{ fmt(b.v) }}円</title>
            </circle>
          </svg>
          <div class="text-[10px] text-gray-400 flex justify-between mt-1">
            <span>{{ trend[0]?.year_month }}</span>
            <span>{{ trend[trend.length - 1]?.year_month }}</span>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
