<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  labels: Array,
})

const form = reactive({
  name: '',
  is_off: false,
  display_order: 0,
  work_hours: null,
})

const editingId = ref(null)
const editForm = reactive({
  name: '',
  is_off: false,
  display_order: 0,
  work_hours: null,
})

const store = () => {
  router.post(route('shift-labels.store'), form, {
    onSuccess: () => {
      form.name = ''
      form.is_off = false
      form.display_order = 0
      form.work_hours = null
    },
  })
}

const startEdit = (label) => {
  editingId.value = label.id
  editForm.name = label.name
  editForm.is_off = !!label.is_off
  editForm.display_order = label.display_order
  editForm.work_hours = label.work_hours != null ? Number(label.work_hours) : null
}

const cancelEdit = () => {
  editingId.value = null
}

const update = (label) => {
  router.patch(route('shift-labels.update', label.id), editForm, {
    preserveScroll: true,
    onSuccess: () => {
      editingId.value = null
    },
  })
}

const destroy = (label) => {
  if (confirm(`「${label.name}」を削除しますか？`)) {
    router.delete(route('shift-labels.destroy', label.id))
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
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <FlashMessage />

          <!-- 既存ラベル一覧 -->
          <table class="w-full text-sm mb-6">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="py-2 px-2">ラベル名</th>
                <th class="py-2 px-2 w-20 text-center">休み系</th>
                <th class="py-2 px-2 w-24 text-center">勤務時間</th>
                <th class="py-2 px-2 w-20 text-center">表示順</th>
                <th class="py-2 px-2 w-16"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="l in labels" :key="l.id">
                <!-- 通常表示行 -->
                <tr v-if="editingId !== l.id" class="border-b">
                  <td class="py-2 px-2">
                    <span :class="l.is_off ? 'text-gray-500' : 'text-gray-900'">{{ l.name }}</span>
                  </td>
                  <td class="py-2 px-2 text-center">
                    <span v-if="l.is_off" class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-md">休み系</span>
                  </td>
                  <td class="py-2 px-2 text-center">
                    <span v-if="l.is_off" class="text-gray-300">—</span>
                    <span v-else-if="l.work_hours != null" class="text-gray-700">{{ l.work_hours }} h</span>
                    <span v-else class="text-amber-500 text-xs" title="パート職員の人件費計算に必要">未設定</span>
                  </td>
                  <td class="py-2 px-2 text-center text-gray-500">{{ l.display_order }}</td>
                  <td class="py-2 px-2 text-right whitespace-nowrap">
                    <button @click="startEdit(l)"
                      class="text-xs px-2 py-1 border border-blue-200 text-blue-500 rounded-md hover:bg-blue-50 mr-1">
                      編集
                    </button>
                    <button v-if="!isProtected(l)" @click="destroy(l)"
                      class="text-xs px-2 py-1 border border-red-200 text-red-400 rounded-md hover:bg-red-50">
                      削除
                    </button>
                  </td>
                </tr>
                <!-- 編集行 -->
                <tr v-else class="border-b bg-blue-50/40">
                  <td class="py-2 px-2">
                    <input v-model="editForm.name" type="text" maxlength="30"
                      :disabled="isProtected(l)"
                      class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm disabled:bg-gray-100 disabled:text-gray-500" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input v-model="editForm.is_off" type="checkbox"
                      :disabled="isProtected(l)"
                      class="rounded-md" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input v-model.number="editForm.work_hours" type="number" min="0" max="24" step="0.25"
                      :disabled="editForm.is_off"
                      class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input v-model.number="editForm.display_order" type="number" min="0"
                      class="w-16 border border-gray-300 rounded-md px-2 py-1 text-sm" />
                  </td>
                  <td class="py-2 px-2 text-right whitespace-nowrap">
                    <button @click="update(l)"
                      class="text-xs px-2 py-1 bg-green-500 text-white rounded-md hover:bg-green-600 mr-1">
                      保存
                    </button>
                    <button @click="cancelEdit"
                      class="text-xs px-2 py-1 border border-gray-300 text-gray-500 rounded-md hover:bg-gray-50">
                      取消
                    </button>
                  </td>
                </tr>
              </template>
              <tr v-if="labels.length === 0">
                <td colspan="5" class="py-4 text-center text-gray-400">ラベルがありません</td>
              </tr>
            </tbody>
          </table>

          <!-- 新規追加フォーム -->
          <div class="border-t pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">ラベル追加</h3>
            <form @submit.prevent="store" class="flex items-end gap-3 flex-wrap">
              <div class="flex-1 min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">ラベル名</label>
                <input v-model="form.name" type="text" maxlength="30" placeholder="例: 早番"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
              </div>
              <div class="w-28">
                <label class="block text-xs text-gray-500 mb-1">勤務時間(h)</label>
                <input v-model.number="form.work_hours" type="number" min="0" max="24" step="0.25"
                  :disabled="form.is_off"
                  placeholder="8"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
              <div class="w-20">
                <label class="block text-xs text-gray-500 mb-1">表示順</label>
                <input v-model.number="form.display_order" type="number" min="0"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
              </div>
              <div class="flex items-center gap-1 pb-1">
                <input v-model="form.is_off" type="checkbox" id="is_off" class="rounded-md" />
                <label for="is_off" class="text-xs text-gray-500">休み系</label>
              </div>
              <button type="submit"
                class="px-4 py-2 text-sm bg-green-500 text-white rounded-md hover:bg-green-600 whitespace-nowrap">
                追加
              </button>
            </form>
            <p class="mt-2 text-xs text-gray-500">
              <span class="font-medium">勤務時間(h)</span>は休憩を除く実働時間。パート職員の人件費 = 時給 × 勤務時間 の計算に使います。
            </p>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
