<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  year: Number,
  months: Array,
})

const STATUS_LABELS = {
  draft:     { label: '下書き', class: 'bg-amber-100 text-amber-700' },
  confirmed: { label: '確定',   class: 'bg-green-100 text-green-700' },
}

const selectedYear = ref(props.year)
const page = usePage()
const billingContext = computed(() => {
  const query = new URLSearchParams(page.url.split('?')[1]?.split('#')[0])
  const period = query.get('billing_period')
  const date = query.get('billing_date')
  return /^[1-9]\d*$/.test(period ?? '') && /^\d{4}-\d{2}-\d{2}$/.test(date ?? '')
    ? { billing_period: period, billing_date: date }
    : {}
})

const changeYear = () => {
  router.get(route('shifts.index'), { year: selectedYear.value, ...billingContext.value }, { preserveState: true, replace: true })
}

const createShift = (month) => {
  router.post(route('shifts.create', billingContext.value), { year: selectedYear.value, month })
}

const destroyShift = (shift) => {
  if (confirm(`${selectedYear.value}年${shift.month}月のシフトを削除しますか？`)) {
    router.delete(route('shifts.destroy', shift.id))
  }
}
</script>

<template>
  <Head title="シフト管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-xl text-gray-800">シフト管理</h2>
        <Link v-if="billingContext.billing_period" :href="route('billing.show', { billingPeriod: billingContext.billing_period, check: 1 })"
          class="text-sm font-medium text-primary-700 underline">請求チェックに戻る</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <FlashMessage />

          <!-- ラベル設定リンク -->
          <div v-if="$page.props.auth.staff_role === 'admin'" class="flex justify-end mb-4">
            <Link :href="route('shift-labels.index')"
              class="text-xs px-3 py-1 border rounded-md hover:bg-gray-50 text-gray-600">
              ラベル設定
            </Link>
          </div>

          <!-- 年選択 -->
          <div class="flex items-center gap-3 mb-6">
            <button @click="selectedYear--; changeYear()"
              class="px-2 py-1 border rounded-md text-gray-500 hover:bg-gray-50">&larr;</button>
            <span class="text-lg font-semibold">{{ selectedYear }}年</span>
            <button @click="selectedYear++; changeYear()"
              class="px-2 py-1 border rounded-md text-gray-500 hover:bg-gray-50">&rarr;</button>
          </div>

          <!-- 月一覧テーブル -->
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="py-2 px-3">月</th>
                <th class="py-2 px-3">ステータス</th>
                <th class="py-2 px-3">作成日</th>
                <th class="py-2 px-3 text-right">アクション</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in months" :key="m.month" :id="`shift-month-${m.month}`"
                class="scroll-mt-4 border-b hover:bg-gray-50"
                :class="{ 'bg-primary-50': billingContext.billing_date?.slice(0, 7) === `${selectedYear}-${String(m.month).padStart(2, '0')}` }">
                <td class="py-3 px-3 font-medium">{{ m.month }}月</td>
                <td class="py-3 px-3">
                  <span v-if="m.status"
                    :class="['px-2 py-0.5 rounded-md text-xs font-medium', STATUS_LABELS[m.status]?.class]">
                    {{ STATUS_LABELS[m.status]?.label }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">未作成</span>
                </td>
                <td class="py-3 px-3 text-gray-500">{{ m.created_at ?? '' }}</td>
                <td class="py-3 px-3 text-right">
                  <div class="flex justify-end gap-2">
                    <template v-if="m.id">
                      <Link :href="route('shifts.edit', { shift: m.id, ...billingContext })"
                        class="text-xs px-3 py-1 border rounded-md hover:bg-gray-50">
                        {{ ['admin','leader'].includes($page.props.auth.staff_role) ? '編集' : '閲覧' }}
                      </Link>
                      <button v-if="$page.props.auth.staff_role === 'admin'"
                        @click="destroyShift(m)"
                        class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded-md hover:bg-red-50">
                        削除
                      </button>
                    </template>
                    <button v-else-if="$page.props.auth.staff_role === 'admin'"
                      @click="createShift(m.month)"
                      class="text-xs px-3 py-1 bg-green-500 text-white rounded-md hover:bg-green-600">
                      ＋ 新規作成
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
