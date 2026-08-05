<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  schools: Array,
})

const csvFile = ref(null)
const uploading = ref(false)
const importErrors = ref([])

const onFileChange = (e) => {
  csvFile.value = e.target.files[0] || null
}

const submit = () => {
  if (!csvFile.value) {
    alert('CSVファイルを選択してください')
    return
  }
  const fd = new FormData()
  fd.append('csv_file', csvFile.value)
  uploading.value = true
  importErrors.value = []
  router.post(route('children.bulk.store'), fd, {
    forceFormData: true,
    onSuccess: (page) => {
      importErrors.value = page.props.flash?.import_errors ?? []
    },
    onFinish: () => { uploading.value = false },
  })
}
</script>

<template>
  <Head title="児童の一括登録（CSV）" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">児童の一括登録（CSV）</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <FlashMessage />
        <BreezeValidationErrors />

        <div v-if="importErrors.length" class="p-4 bg-rose-50 border border-rose-200 rounded-lg">
          <p class="font-semibold text-rose-800 mb-2">エラーが見つかったため、登録は行われませんでした。</p>
          <ul class="text-sm text-rose-700 list-disc list-inside space-y-1">
            <li v-for="(err, i) in importErrors" :key="i">{{ err }}</li>
          </ul>
        </div>

        <!-- 注意事項 -->
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
          この一括登録では<strong>基本情報のみ</strong>を登録します。障がい種別・アレルギー・配慮事項などの
          要配慮個人情報と保護者の紐付けは対象外です。登録後、各児童の編集画面から個別に入力してください。
        </div>

        <!-- 使い方 -->
        <div class="p-6 bg-white border border-gray-200 rounded-lg space-y-4">
          <h3 class="text-lg font-semibold text-gray-900">使い方</h3>
          <ol class="list-decimal list-inside text-sm text-gray-700 space-y-1">
            <li>下の「テンプレートCSVをダウンロード」から CSV をダウンロードします。</li>
            <li>Excel 等で開き、児童情報を入力します（学校名は下の一覧と完全一致させてください）。</li>
            <li>保存して「CSVファイルを選択」からアップロードし、「一括登録」を押してください。</li>
            <li>すべての行にエラーがない場合のみ登録されます（1行でもエラーがあれば全件中止）。</li>
          </ol>

          <a
            :href="route('children.bulk.template')"
            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-semibold rounded-lg hover:bg-primary-700"
          >
            テンプレートCSVをダウンロード
          </a>
        </div>

        <!-- CSV項目の説明 -->
        <div class="p-6 bg-gray-50 border border-gray-200 rounded-lg">
          <h3 class="text-base font-semibold text-gray-900 mb-3">CSVの項目</h3>
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase border-b">
              <tr>
                <th class="py-2 pr-4">列名</th>
                <th class="py-2 pr-4">必須</th>
                <th class="py-2">説明</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr><td class="py-2 pr-4 font-mono">name</td><td>必須</td><td class="py-2">児童名</td></tr>
              <tr><td class="py-2 pr-4 font-mono">name_kana</td><td>任意</td><td class="py-2">児童名カナ（カタカナ）</td></tr>
              <tr><td class="py-2 pr-4 font-mono">gender</td><td>任意</td><td class="py-2">male / female / other</td></tr>
              <tr><td class="py-2 pr-4 font-mono">birthdate</td><td>任意</td><td class="py-2">生年月日（例: 2015-04-01）</td></tr>
              <tr><td class="py-2 pr-4 font-mono">grade</td><td>任意</td><td class="py-2">学年（例: 小3）</td></tr>
              <tr><td class="py-2 pr-4 font-mono">school_name</td><td>任意</td><td class="py-2">事業所に登録済みの学校名と完全一致</td></tr>
              <tr><td class="py-2 pr-4 font-mono">pickup_address</td><td>任意</td><td class="py-2">送迎先住所</td></tr>
              <tr><td class="py-2 pr-4 font-mono">contract_start_date</td><td>任意</td><td class="py-2">契約開始日（例: 2026-04-01）</td></tr>
              <tr><td class="py-2 pr-4 font-mono">contract_status</td><td>任意（省略時 active）</td><td class="py-2">active / suspended / ended</td></tr>
            </tbody>
          </table>
        </div>

        <!-- 登録済み学校の一覧 -->
        <div class="p-4 bg-white border rounded-lg">
          <h4 class="font-semibold text-gray-800 mb-2">登録済みの学校</h4>
          <p v-if="schools.length === 0" class="text-sm text-gray-500">
            未登録です。<Link :href="route('schools.index')" class="text-blue-500 underline">学校を登録</Link>
          </p>
          <ul v-else class="text-sm text-gray-700 list-disc list-inside space-y-0.5">
            <li v-for="s in schools" :key="s.id">{{ s.name }}</li>
          </ul>
        </div>

        <!-- アップロードフォーム -->
        <div class="p-6 bg-white border border-gray-200 rounded-lg">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">CSVをアップロード</h3>
          <form @submit.prevent="submit">
            <input
              type="file"
              accept=".csv,text/csv"
              class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer focus:outline-none p-2"
              @change="onFileChange"
            />
            <div class="flex items-center justify-end mt-4">
              <Link :href="route('children.create')" class="text-sm text-gray-500 mr-4">単体登録に戻る</Link>
              <button
                type="submit"
                :disabled="uploading"
                class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600 disabled:opacity-50"
              >
                {{ uploading ? 'アップロード中…' : '一括登録' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
