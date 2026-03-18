<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

defineProps({
  programs: Array,
})

const CATEGORY_LABELS = {
  physical:    { label: '運動',         class: 'bg-blue-100   text-blue-700'  },
  cognitive:   { label: '認知・学習',   class: 'bg-purple-100 text-purple-700' },
  social:      { label: '社会性・SST',  class: 'bg-green-100  text-green-700'  },
  life_skills: { label: '生活スキル',   class: 'bg-orange-100 text-orange-700' },
  other:       { label: 'その他',       class: 'bg-gray-100   text-gray-600'   },
}

const destroy = (program) => {
  if (confirm(`「${program.name}」を削除しますか？`)) {
    Inertia.delete(route('programs.destroy', program.id))
  }
}

// カテゴリでグループ化
const grouped = (programs) => {
  const order = ['physical', 'cognitive', 'social', 'life_skills', 'other']
  return order
    .map(cat => ({
      category: cat,
      items: programs.filter(p => p.category === cat),
    }))
    .filter(g => g.items.length > 0)
}
</script>

<template>
  <Head title="療育プログラム管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">療育プログラム管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />

          <div class="flex justify-end mb-6">
            <Link
              :href="route('programs.create')"
              class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600"
            >＋ プログラム登録</Link>
          </div>

          <!-- カテゴリ別一覧 -->
          <div v-for="group in grouped(programs)" :key="group.category" class="mb-8">
            <div class="flex items-center gap-2 mb-3">
              <span :class="['px-3 py-1 rounded-full text-sm font-medium', CATEGORY_LABELS[group.category].class]">
                {{ CATEGORY_LABELS[group.category].label }}
              </span>
              <span class="text-sm text-gray-400">{{ group.items.length }}件</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div
                v-for="p in group.items"
                :key="p.id"
                :class="['flex items-center justify-between p-4 border rounded-lg', !p.is_active && 'opacity-50 bg-gray-50']"
              >
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900 text-sm">{{ p.name }}</span>
                    <span v-if="!p.is_active" class="text-xs text-gray-400">（無効）</span>
                  </div>
                  <div class="text-xs text-gray-500 mt-1">標準 {{ p.duration_minutes }}分</div>
                </div>
                <div class="flex gap-2">
                  <Link
                    :href="route('programs.edit', p.id)"
                    class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600"
                  >編集</Link>
                  <button
                    @click="destroy(p)"
                    class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded hover:bg-red-50"
                  >削除</button>
                </div>
              </div>
            </div>
          </div>

          <p v-if="programs.length === 0" class="text-center text-gray-400 py-8">
            プログラムが登録されていません。「＋ プログラム登録」から追加してください。
          </p>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
