<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  twoFactor: Object,
})

const confirmForm = useForm({ code: '' })

const enable = () => {
  router.post(route('two-factor.enable'), {}, { preserveScroll: true })
}

const confirm = () => {
  confirmForm.post(route('two-factor.confirm'), {
    preserveScroll: true,
    onSuccess: () => confirmForm.reset(),
  })
}

const regenerate = () => {
  if (window.confirm('リカバリコードを再生成すると、以前のコードはすべて使えなくなります。よろしいですか？')) {
    router.post(route('two-factor.recovery-codes'), {}, { preserveScroll: true })
  }
}

const disable = () => {
  if (window.confirm('二要素認証を無効にしますか？アカウントの保護レベルが下がります。')) {
    router.delete(route('two-factor.disable'), { preserveScroll: true })
  }
}

const copyRecoveryCodes = () => {
  navigator.clipboard?.writeText(props.twoFactor.recoveryCodes.join('\n'))
}
</script>

<template>
  <Head title="アカウントのセキュリティ" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">アカウントのセキュリティ</h2>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />
        <BreezeValidationErrors />

        <div class="bg-white border border-gray-200 sm:rounded-lg p-6 space-y-4">
          <h3 class="text-base font-semibold text-gray-800 border-b pb-2">二要素認証（2FA）</h3>

          <!-- 未設定 -->
          <div v-if="!twoFactor.enabled && !twoFactor.pending" class="space-y-4">
            <p class="text-sm text-gray-600">
              ログイン時にパスワードに加えて認証アプリの6桁コードを要求し、アカウントを保護します。
              児童の要配慮個人情報を扱うため、<span class="font-medium">全職員に設定を推奨</span>します。
            </p>
            <button
              @click="enable"
              class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-md"
            >
              二要素認証を有効にする
            </button>
          </div>

          <!-- 有効化中（QR読み取り待ち） -->
          <div v-else-if="twoFactor.pending" class="space-y-4">
            <ol class="list-decimal list-inside text-sm text-gray-700 space-y-1">
              <li>スマートフォンの認証アプリ（Google Authenticator / Microsoft Authenticator 等）で下のQRコードを読み取る</li>
              <li>アプリに表示された6桁のコードを入力して「確認して有効化」を押す</li>
            </ol>

            <div class="flex flex-col sm:flex-row gap-6 items-start">
              <div class="p-3 bg-white border rounded-md" v-html="twoFactor.qrCodeSvg"></div>
              <div class="text-sm text-gray-600 space-y-2">
                <p>QRコードを読み取れない場合は、次のセットアップキーを認証アプリに手入力してください。</p>
                <p class="font-mono bg-gray-100 rounded-md px-2 py-1 break-all select-all">{{ twoFactor.secretKey }}</p>
              </div>
            </div>

            <form @submit.prevent="confirm" class="flex items-end gap-3">
              <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">認証コード（6桁）</label>
                <input
                  id="code"
                  v-model="confirmForm.code"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  class="border border-gray-300 rounded-md px-3 py-2 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-primary-300"
                  autofocus
                />
              </div>
              <button
                :disabled="confirmForm.processing"
                class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-md disabled:opacity-25"
              >
                確認して有効化
              </button>
              <button
                type="button"
                @click="disable"
                class="text-sm text-gray-500 underline hover:text-gray-700 pb-2"
              >
                キャンセル
              </button>
            </form>
          </div>

          <!-- 有効 -->
          <div v-else class="space-y-4">
            <p class="text-sm">
              <span class="inline-flex items-center gap-1 text-green-700 bg-green-50 border border-green-200 rounded-md px-2 py-0.5 font-medium">
                ✓ 有効
              </span>
              <span class="ml-2 text-gray-600">ログイン時に認証アプリのコードが必要です。</span>
            </p>

            <div>
              <h4 class="text-sm font-semibold text-gray-800 mb-2">リカバリコード</h4>
              <p class="text-sm text-gray-600 mb-2">
                スマートフォンを紛失した場合はこのコードでログインできます。
                <span class="font-medium text-red-600">印刷するか安全な場所に保管してください</span>（各コードは1回のみ使用可）。
              </p>
              <div class="bg-gray-100 rounded-md p-3 font-mono text-sm grid grid-cols-1 sm:grid-cols-2 gap-1 select-all">
                <div v-for="code in twoFactor.recoveryCodes" :key="code">{{ code }}</div>
              </div>
              <div class="mt-2 flex gap-3">
                <button @click="copyRecoveryCodes" class="text-sm text-primary-600 underline hover:text-primary-800">
                  コピー
                </button>
                <button @click="regenerate" class="text-sm text-primary-600 underline hover:text-primary-800">
                  再生成
                </button>
              </div>
            </div>

            <div class="border-t pt-4">
              <button
                @click="disable"
                class="bg-white border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-md"
              >
                二要素認証を無効にする
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
