<script setup>
import { Head, useForm } from '@inertiajs/inertia-vue3'

const props = defineProps({
  session_id: String,
  admin_email: String,
})

const form = useForm({
  session_id: props.session_id,
  facility_name: '',
  admin_name: '',
  admin_email: props.admin_email,
  address: '',
  tel: '',
  capacity_per_day: 10,
})

const submit = () => {
  form.post('/subscribe/store')
}
</script>

<template>
  <Head title="事業所情報の登録 | ハグくむ" />

  <div class="min-h-screen bg-gray-50 font-sans antialiased">
    <div class="max-w-lg mx-auto px-6 py-16">

      <div class="text-center mb-10">
        <span class="text-xl font-extrabold tracking-tight"><span class="bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">ハグ</span><span class="text-gray-900">くむ</span></span>
        <h1 class="text-2xl font-extrabold text-gray-900 mt-4">事業所情報の登録</h1>
        <p class="text-sm text-gray-400 mt-2">決済が完了しました。事業所の基本情報を入力してください。</p>
      </div>

      <div v-if="$page.props.flash?.error"
        class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-4 mb-6">
        {{ $page.props.flash.error }}
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 space-y-5">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            事業所名 <span class="text-red-500">*</span>
          </label>
          <input v-model="form.facility_name" type="text" required
            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="例: 放デイすまいる">
          <p v-if="form.errors.facility_name" class="text-red-500 text-xs mt-1">{{ form.errors.facility_name }}</p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            管理者氏名 <span class="text-red-500">*</span>
          </label>
          <input v-model="form.admin_name" type="text" required
            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="例: 山田太郎">
          <p v-if="form.errors.admin_name" class="text-red-500 text-xs mt-1">{{ form.errors.admin_name }}</p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">メールアドレス</label>
          <input :value="admin_email" type="email" disabled
            class="w-full border border-gray-100 bg-gray-50 rounded-lg px-4 py-2.5 text-sm text-gray-500">
          <p class="text-xs text-gray-400 mt-1">決済時に入力されたメールアドレスです</p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">住所</label>
          <input v-model="form.address" type="text"
            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="例: 東京都渋谷区...">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">電話番号</label>
          <input v-model="form.tel" type="text"
            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="例: 03-1234-5678">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">1日の定員</label>
          <input v-model="form.capacity_per_day" type="number" min="1"
            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <button type="submit" :disabled="form.processing"
          class="w-full py-3.5 bg-gray-900 text-white text-sm font-bold rounded-full hover:bg-gray-800 transition disabled:opacity-50">
          {{ form.processing ? '登録中...' : '登録する' }}
        </button>
      </form>

    </div>
  </div>
</template>
