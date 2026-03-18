<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive, ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  program: Object,
})

const CATEGORY_OPTIONS = [
  { value: 'physical',    label: '運動' },
  { value: 'cognitive',   label: '認知・学習' },
  { value: 'social',      label: '社会性・SST' },
  { value: 'life_skills', label: '生活スキル' },
  { value: 'other',       label: 'その他' },
]

const form = reactive({
  name:             props.program.name ?? '',
  category:         props.program.category ?? 'other',
  description:      props.program.description ?? '',
  duration_minutes: props.program.duration_minutes ?? 30,
  is_active:        props.program.is_active ?? true,
})

const update = () => {
  Inertia.patch(route('programs.update', props.program.id), form)
}

// 項目追加フォーム
const newItem = reactive({ name: '', difficulty_order: 0 })
const addItem = () => {
  if (!newItem.name.trim()) return
  Inertia.post(route('program-items.store', props.program.id), { ...newItem }, {
    preserveScroll: true,
    onSuccess: () => { newItem.name = ''; newItem.difficulty_order = 0 },
  })
}

const deleteItem = (itemId) => {
  if (!confirm('この項目を削除しますか？')) return
  Inertia.delete(route('program-items.destroy', itemId), { preserveScroll: true })
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="program.name + ' - 編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('programs.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">プログラム編集</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <FlashMessage />

        <!-- プログラム基本情報 -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-5">

            <div>
              <label :class="labelClass">カテゴリ <span class="text-red-500">*</span></label>
              <div class="flex flex-wrap gap-2 mt-1">
                <label
                  v-for="opt in CATEGORY_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'px-3 py-2 border rounded cursor-pointer text-sm transition-colors',
                    form.category === opt.value
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.category" :value="opt.value" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
            </div>

            <div>
              <label :class="labelClass">プログラム名 <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" :class="inputClass" />
            </div>

            <div>
              <label :class="labelClass">標準時間（分） <span class="text-red-500">*</span></label>
              <div class="flex items-center gap-3">
                <input v-model="form.duration_minutes" type="number" min="5" max="180" step="5" :class="inputClass" />
                <span class="text-sm text-gray-500 whitespace-nowrap">分</span>
              </div>
            </div>

            <div>
              <label :class="labelClass">説明・ねらい</label>
              <textarea v-model="form.description" :class="inputClass" rows="3" />
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="w-4 h-4" />
              <label for="is_active" class="text-sm text-gray-700">有効にする</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('programs.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                更新する
              </button>
            </div>
          </form>
        </div>

        <!-- 詳細項目管理 -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h3 class="text-sm font-semibold text-gray-700 mb-1">詳細項目（支援記録で選択可能）</h3>
          <p class="text-xs text-gray-400 mb-4">例：トランポリンの「ストレートジャンプ」「膝タッチ」など。難易度順に並びます。</p>

          <!-- 既存項目リスト -->
          <div v-if="program.items?.length" class="mb-4 space-y-2">
            <div
              v-for="item in program.items"
              :key="item.id"
              class="flex items-center justify-between px-3 py-2 bg-gray-50 border border-gray-200 rounded text-sm"
            >
              <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 w-6 text-right">{{ item.difficulty_order }}</span>
                <span class="text-gray-700">{{ item.name }}</span>
              </div>
              <button
                type="button"
                @click="deleteItem(item.id)"
                class="text-xs text-red-400 hover:text-red-600"
              >削除</button>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 mb-4">まだ項目がありません</p>

          <!-- 新規追加フォーム -->
          <div class="flex gap-2 items-end">
            <div class="flex-1">
              <label class="block text-xs text-gray-500 mb-1">項目名</label>
              <input
                v-model="newItem.name"
                type="text"
                placeholder="例: ストレートジャンプ"
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                @keyup.enter="addItem"
              />
            </div>
            <div class="w-20">
              <label class="block text-xs text-gray-500 mb-1">難易度</label>
              <input
                v-model="newItem.difficulty_order"
                type="number"
                min="0"
                max="999"
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-300"
              />
            </div>
            <button
              type="button"
              @click="addItem"
              class="px-4 py-2 bg-green-500 text-white text-sm rounded hover:bg-green-600 whitespace-nowrap"
            >＋ 追加</button>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
