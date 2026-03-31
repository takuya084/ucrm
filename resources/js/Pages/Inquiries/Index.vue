<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import QuickNav from '@/Components/QuickNav.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  inquiries:      Object,
  filters:        Object,
  statusLabels:   Object,
  categoryLabels: Object,
})

const filterStatus   = ref(props.filters.status   ?? '')
const filterCategory = ref(props.filters.category ?? '')
const filterEscalated = ref(props.filters.escalated ?? false)

const applyFilters = () => {
  Inertia.get(route('inquiries.index'), {
    status:    filterStatus.value   || undefined,
    category:  filterCategory.value || undefined,
    escalated: filterEscalated.value || undefined,
  }, { preserveState: true, replace: true })
}

const STATUS_COLOR = {
  open:        'bg-red-100 text-red-700',
  in_progress: 'bg-yellow-100 text-yellow-700',
  closed:      'bg-gray-100 text-gray-500',
}
</script>

<template>
  <Head title="問い合わせ管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">問い合わせ管理</h2>
        <Link :href="route('inquiries.create')" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">
          ＋ 新規登録
        </Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <QuickNav />
        <FlashMessage />

        <!-- フィルター -->
        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">ステータス</label>
            <select v-model="filterStatus" @change="applyFilters" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="(label, val) in statusLabels" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">カテゴリ</label>
            <select v-model="filterCategory" @change="applyFilters" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="(label, val) in categoryLabels" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="filterEscalated" type="checkbox" class="w-4 h-4" @change="applyFilters" />
            エスカレーションのみ
          </label>
        </div>

        <!-- 一覧 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="inquiries.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
            条件に一致する問い合わせがありません
          </div>
          <ul v-else class="divide-y">
            <li
              v-for="inq in inquiries.data"
              :key="inq.id"
              class="px-5 py-4 hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[inq.status]]">
                      {{ statusLabels[inq.status] }}
                    </span>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                      {{ categoryLabels[inq.category] }}
                    </span>
                    <span v-if="inq.is_escalated" class="text-xs text-red-600 font-bold">⚠ エスカレ</span>
                  </div>
                  <div class="mt-1 flex items-center gap-2">
                    <Link :href="route('inquiries.show', inq.id)" class="text-sm font-medium text-gray-900 hover:text-indigo-600 truncate">
                      {{ inq.subject || inq.content?.slice(0, 40) + '…' }}
                    </Link>
                  </div>
                  <div class="text-xs text-gray-500 mt-0.5">
                    {{ inq.child?.name }} — {{ inq.contacted_at?.slice(0, 10) }}
                    <span v-if="inq.staff">（対応：{{ inq.staff.name }}）</span>
                  </div>
                </div>
                <Link :href="route('inquiries.show', inq.id)" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">
                  詳細
                </Link>
              </div>
            </li>
          </ul>

          <!-- ページネーション -->
          <div v-if="inquiries.last_page > 1" class="px-5 py-3 border-t flex gap-2 text-sm">
            <Link
              v-for="link in inquiries.links"
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
