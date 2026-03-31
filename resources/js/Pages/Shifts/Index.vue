<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  year: Number,
  months: Array,
})

const STATUS_LABELS = {
  draft:     { label: '下書き', class: 'bg-yellow-100 text-yellow-700' },
  confirmed: { label: '確定',   class: 'bg-green-100 text-green-700' },
}

const selectedYear = ref(props.year)

const changeYear = () => {
  Inertia.get(route('shifts.index'), { year: selectedYear.value }, { preserveState: true, replace: true })
}

const createShift = (month) => {
  Inertia.post(route('shifts.create'), { year: selectedYear.value, month })
}

const destroyShift = (shift) => {
  if (confirm(`${selectedYear.value}年${shift.month}月のシフトを削除しますか？`)) {
    Inertia.delete(route('shifts.destroy', shift.id))
  }
}
</script>

<template>
  <Head title="シフト管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">シフト管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />

          <!-- ラベル設定リンク -->
          <div v-if="$page.props.auth.staff_role === 'admin'" class="flex justify-end mb-4">
            <Link :href="route('shift-labels.index')"
              class="text-xs px-3 py-1 border rounded hover:bg-gray-50 text-gray-600">
              ラベル設定
            </Link>
          </div>

          <!-- 年選択 -->
          <div class="flex items-center gap-3 mb-6">
            <button @click="selectedYear--; changeYear()"
              class="px-2 py-1 border rounded text-gray-500 hover:bg-gray-50">&larr;</button>
            <span class="text-lg font-semibold">{{ selectedYear }}年</span>
            <button @click="selectedYear++; changeYear()"
              class="px-2 py-1 border rounded text-gray-500 hover:bg-gray-50">&rarr;</button>
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
              <tr v-for="m in months" :key="m.month" class="border-b hover:bg-gray-50">
                <td class="py-3 px-3 font-medium">{{ m.month }}月</td>
                <td class="py-3 px-3">
                  <span v-if="m.status"
                    :class="['px-2 py-0.5 rounded text-xs font-medium', STATUS_LABELS[m.status]?.class]">
                    {{ STATUS_LABELS[m.status]?.label }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">未作成</span>
                </td>
                <td class="py-3 px-3 text-gray-500">{{ m.created_at ?? '' }}</td>
                <td class="py-3 px-3 text-right">
                  <div class="flex justify-end gap-2">
                    <template v-if="m.id">
                      <Link :href="route('shifts.edit', m.id)"
                        class="text-xs px-3 py-1 border rounded hover:bg-gray-50">
                        {{ ['admin','leader'].includes($page.props.auth.staff_role) ? '編集' : '閲覧' }}
                      </Link>
                      <button v-if="$page.props.auth.staff_role === 'admin'"
                        @click="destroyShift(m)"
                        class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded hover:bg-red-50">
                        削除
                      </button>
                    </template>
                    <button v-else-if="$page.props.auth.staff_role === 'admin'"
                      @click="createShift(m.month)"
                      class="text-xs px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600">
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
