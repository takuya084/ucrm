<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  labels: Array,
})

const form = reactive({
  name: '',
  is_off: false,
  display_order: 0,
})

const store = () => {
  Inertia.post(route('shift-labels.store'), form, {
    onSuccess: () => {
      form.name = ''
      form.is_off = false
      form.display_order = 0
    },
  })
}

const destroy = (label) => {
  if (confirm(`「${label.name}」を削除しますか？`)) {
    Inertia.delete(route('shift-labels.destroy', label.id))
  }
}

const isProtected = (label) => label.is_off && ['休み', '有給'].includes(label.name)
</script>

<template>
  <Head title="シフトラベル設定" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('shifts.index')" class="text-gray-400 hover:text-gray-600 text-sm">← シフト管理へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">シフトラベル設定</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />

          <!-- 既存ラベル一覧 -->
          <table class="w-full text-sm mb-6">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="py-2 px-2">ラベル名</th>
                <th class="py-2 px-2 w-20 text-center">休み系</th>
                <th class="py-2 px-2 w-20 text-center">表示順</th>
                <th class="py-2 px-2 w-16"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in labels" :key="l.id" class="border-b">
                <td class="py-2 px-2">
                  <span :class="l.is_off ? 'text-gray-500' : 'text-gray-900'">{{ l.name }}</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span v-if="l.is_off" class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded">休み系</span>
                </td>
                <td class="py-2 px-2 text-center text-gray-500">{{ l.display_order }}</td>
                <td class="py-2 px-2 text-right">
                  <button v-if="!isProtected(l)" @click="destroy(l)"
                    class="text-xs px-2 py-1 border border-red-200 text-red-400 rounded hover:bg-red-50">
                    削除
                  </button>
                </td>
              </tr>
              <tr v-if="labels.length === 0">
                <td colspan="4" class="py-4 text-center text-gray-400">ラベルがありません</td>
              </tr>
            </tbody>
          </table>

          <!-- 新規追加フォーム -->
          <div class="border-t pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">ラベル追加</h3>
            <form @submit.prevent="store" class="flex items-end gap-3">
              <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">ラベル名</label>
                <input v-model="form.name" type="text" maxlength="30" placeholder="例: 早番"
                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
              </div>
              <div class="w-20">
                <label class="block text-xs text-gray-500 mb-1">表示順</label>
                <input v-model.number="form.display_order" type="number" min="0"
                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
              </div>
              <div class="flex items-center gap-1 pb-1">
                <input v-model="form.is_off" type="checkbox" id="is_off" class="rounded" />
                <label for="is_off" class="text-xs text-gray-500">休み系</label>
              </div>
              <button type="submit"
                class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600 whitespace-nowrap">
                追加
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
