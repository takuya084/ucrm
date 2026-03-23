<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  roleLabels: Object,
})

const form = reactive({
  name:  '',
  email: '',
  role:  'staff',
})

const store = () => {
  Inertia.post(route('staff.store'), form)
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head title="職員追加" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('staff.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">職員追加</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="store" class="space-y-5">

            <!-- 氏名 -->
            <div>
              <label :class="labelClass">氏名 <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" :class="inputClass" placeholder="例：山田太郎" />
            </div>

            <!-- メールアドレス -->
            <div>
              <label :class="labelClass">メールアドレス <span class="text-red-500">*</span></label>
              <input v-model="form.email" type="email" :class="inputClass" placeholder="例：yamada@example.com" />
              <p class="text-xs text-gray-400 mt-1">パスワード設定メールがこのアドレスに送信されます</p>
            </div>

            <!-- 役割 -->
            <div>
              <label :class="labelClass">役割 <span class="text-red-500">*</span></label>
              <div class="flex flex-wrap gap-2 mt-1">
                <label
                  v-for="(label, value) in roleLabels"
                  :key="value"
                  :class="[
                    'px-3 py-2 border rounded cursor-pointer text-sm transition-colors',
                    form.role === value
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.role" :value="value" class="sr-only" />
                  {{ label }}
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('staff.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
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
