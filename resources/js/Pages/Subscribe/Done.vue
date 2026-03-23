<script setup>
import { Head, useForm } from '@inertiajs/inertia-vue3'

const props = defineProps({
  type: String,
  message: String,
  email: String,
})

const resendForm = useForm({
  email: props.email || '',
})

const resend = () => {
  resendForm.email = props.email || ''
  resendForm.post('/subscribe/resend-reset-link')
}
</script>

<template>
  <Head title="登録完了 | ハグくむ" />

  <div class="min-h-screen bg-gray-50 font-sans antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-md mx-auto text-center">

      <div class="mb-8">
        <span class="text-xl font-extrabold tracking-tight"><span class="bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">ハグ</span><span class="text-gray-900">くむ</span></span>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 sm:p-10">

        <!-- 成功メッセージ -->
        <div v-if="type === 'success'" class="mb-6">
          <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 class="text-xl font-extrabold text-gray-900 mb-3">登録が完了しました</h1>
          <p class="text-sm text-gray-500 leading-relaxed">{{ message }}</p>
        </div>

        <!-- エラーメッセージ -->
        <div v-else-if="type === 'error'" class="mb-6">
          <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h1 class="text-xl font-extrabold text-gray-900 mb-3">登録完了</h1>
          <p class="text-sm text-red-600 leading-relaxed">{{ message }}</p>
        </div>

        <!-- メッセージなし（直接アクセス） -->
        <div v-else class="mb-6">
          <h1 class="text-xl font-extrabold text-gray-900 mb-3">登録完了</h1>
          <p class="text-sm text-gray-500 leading-relaxed">登録が完了しています。メールをご確認ください。</p>
        </div>

        <!-- メールアドレス表示 -->
        <div v-if="email" class="bg-gray-50 rounded-xl p-4 mb-6">
          <p class="text-xs text-gray-400 mb-1">送信先メールアドレス</p>
          <p class="text-sm font-semibold text-gray-700 break-all">{{ email }}</p>
        </div>

        <div class="space-y-3">
          <button v-if="email"
            @click="resend" :disabled="resendForm.processing"
            class="w-full py-3 px-6 bg-white border border-gray-200 text-sm font-semibold text-gray-700 rounded-full hover:bg-gray-50 transition disabled:opacity-50 whitespace-nowrap">
            {{ resendForm.processing ? '送信中...' : 'パスワード設定メールを再送する' }}
          </button>

          <a href="/login"
            class="block w-full py-3 px-6 bg-gray-900 text-white text-sm font-bold rounded-full hover:bg-gray-800 transition text-center whitespace-nowrap">
            ログインページへ
          </a>
        </div>
      </div>

    </div>
  </div>
</template>
