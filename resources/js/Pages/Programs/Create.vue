<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const CATEGORY_OPTIONS = [
  { value: 'physical',    label: '運動' },
  { value: 'cognitive',   label: '認知・学習' },
  { value: 'social',      label: '社会性・SST' },
  { value: 'life_skills', label: '生活スキル' },
  { value: 'other',       label: 'その他' },
]

const EXAMPLE_PROGRAMS = {
  physical:    ['トランポリン', 'バランスディスク', '体操', 'ドッジボール'],
  cognitive:   ['宿題支援', 'パズル', 'ワーキングメモリ', '読み書き練習'],
  social:      ['SST', 'グループ活動', 'ロールプレイ', 'お楽しみ会'],
  life_skills: ['着替え練習', '調理活動', '掃除', '金銭管理'],
  other:       ['創作活動', '自由遊び', '音楽活動', '読み聞かせ'],
}

const form = reactive({
  name:             '',
  category:         'other',
  description:      '',
  duration_minutes: 30,
  is_active:        true,
})

const store = () => {
  Inertia.post(route('programs.store'), form)
}

const setExample = (name) => {
  form.name = name
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head title="プログラム登録" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('programs.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">プログラム登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="store" class="space-y-5">

            <!-- カテゴリ -->
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

            <!-- よく使われる例 -->
            <div v-if="EXAMPLE_PROGRAMS[form.category]">
              <p class="text-xs text-gray-500 mb-2">よく使われる例（クリックで入力）：</p>
              <div class="flex flex-wrap gap-1">
                <button
                  v-for="ex in EXAMPLE_PROGRAMS[form.category]"
                  :key="ex"
                  type="button"
                  @click="setExample(ex)"
                  class="px-2 py-1 text-xs border border-gray-200 rounded bg-gray-50 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                >{{ ex }}</button>
              </div>
            </div>

            <!-- プログラム名 -->
            <div>
              <label :class="labelClass">プログラム名 <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" :class="inputClass" placeholder="例：トランポリン" />
            </div>

            <!-- 標準時間 -->
            <div>
              <label :class="labelClass">標準時間（分） <span class="text-red-500">*</span></label>
              <div class="flex items-center gap-3">
                <input v-model="form.duration_minutes" type="number" min="5" max="180" step="5" :class="inputClass" />
                <span class="text-sm text-gray-500 whitespace-nowrap">分</span>
              </div>
            </div>

            <!-- 説明 -->
            <div>
              <label :class="labelClass">説明・ねらい</label>
              <textarea v-model="form.description" :class="inputClass" rows="3"
                placeholder="このプログラムの目的・支援のねらいを入力してください" />
            </div>

            <!-- 有効/無効 -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="w-4 h-4" />
              <label for="is_active" class="text-sm text-gray-700">
                有効にする
                <span class="text-xs text-gray-400">（無効にすると支援記録の選択肢に表示されません）</span>
              </label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('programs.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                登録する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
