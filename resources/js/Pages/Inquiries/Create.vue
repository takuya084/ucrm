<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  children:            Array,
  categoryLabels:      Object,
  contactMethodLabels: Object,
  prefillChildId:      Number,
})

const now = new Date()
const localDatetime = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}T${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`

const form = reactive({
  child_id:       props.prefillChildId ?? '',
  guardian_id:    null,
  contact_method: 'tel',
  contacted_at:   localDatetime,
  category:       'other',
  subject:        '',
  content:        '',
  response:       '',
  status:         'open',
  is_escalated:   false,
})

const store = () => {
  Inertia.post(route('inquiries.store'), form)
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head title="問い合わせ登録" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('inquiries.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">問い合わせ登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="store" class="space-y-5">

            <div>
              <label :class="labelClass">対象児童 <span class="text-red-500">*</span></label>
              <select v-model="form.child_id" :class="inputClass" required>
                <option value="">選択してください</option>
                <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">連絡手段</label>
                <select v-model="form.contact_method" :class="inputClass">
                  <option v-for="(label, val) in contactMethodLabels" :key="val" :value="val">{{ label }}</option>
                </select>
              </div>
              <div>
                <label :class="labelClass">カテゴリ</label>
                <select v-model="form.category" :class="inputClass">
                  <option v-for="(label, val) in categoryLabels" :key="val" :value="val">{{ label }}</option>
                </select>
              </div>
            </div>

            <div>
              <label :class="labelClass">問い合わせ日時 <span class="text-red-500">*</span></label>
              <input v-model="form.contacted_at" type="datetime-local" :class="inputClass" required />
            </div>

            <div>
              <label :class="labelClass">件名</label>
              <input v-model="form.subject" type="text" :class="inputClass" placeholder="件名（省略可）" />
            </div>

            <div>
              <label :class="labelClass">問い合わせ内容 <span class="text-red-500">*</span></label>
              <textarea v-model="form.content" :class="inputClass" rows="4" required placeholder="問い合わせの詳細を記入してください" />
            </div>

            <div>
              <label :class="labelClass">回答・対応内容</label>
              <textarea v-model="form.response" :class="inputClass" rows="3" placeholder="対応した内容、回答した内容など" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">ステータス</label>
                <select v-model="form.status" :class="inputClass">
                  <option value="open">未対応</option>
                  <option value="in_progress">対応中</option>
                  <option value="closed">完了</option>
                </select>
              </div>
              <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                  <input v-model="form.is_escalated" type="checkbox" class="w-4 h-4" />
                  管理者にエスカレーション
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('inquiries.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
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
