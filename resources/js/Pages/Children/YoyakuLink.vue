<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link, usePage } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { ref, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  configured: Boolean,
  unlinkedChildren: Array,
  linkedCount: Number,
  yoyakuUsers: Array,
  yoyakuApiReachable: { type: Boolean, default: null },
})

const page = usePage()
const importErrors = computed(() => page.props.value.flash?.import_errors ?? [])
const linkResults = computed(() => page.props.value.flash?.yoyaku_link_results ?? [])

// --- 新規作成 ---
const selectedIds = ref([])
const creating = ref(false)
const allSelected = computed({
  get: () => props.unlinkedChildren.length > 0 && selectedIds.value.length === props.unlinkedChildren.length,
  set: (v) => { selectedIds.value = v ? props.unlinkedChildren.map(c => c.id) : [] },
})

const submitCreate = () => {
  if (selectedIds.value.length === 0) {
    alert('児童を選択してください')
    return
  }
  creating.value = true
  Inertia.post(route('children.yoyaku-link.create'), { child_ids: selectedIds.value }, {
    onFinish: () => { creating.value = false; selectedIds.value = [] },
  })
}

// --- CSV紐付け ---
const csvFile = ref(null)
const uploading = ref(false)
const onFileChange = (e) => { csvFile.value = e.target.files[0] || null }
const submitCsv = () => {
  if (!csvFile.value) {
    alert('CSVファイルを選択してください')
    return
  }
  const fd = new FormData()
  fd.append('csv_file', csvFile.value)
  uploading.value = true
  Inertia.post(route('children.yoyaku-link.csv'), fd, {
    forceFormData: true,
    onFinish: () => { uploading.value = false; csvFile.value = null },
  })
}
</script>

<template>
  <Head title="p-yoyaku連携: 児童の紐付け" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">p-yoyaku連携: 児童の紐付け</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <FlashMessage />
        <BreezeValidationErrors />

        <div v-if="!configured" class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
          この施設はp-yoyaku連携が未設定です。
          <Link :href="route('facility.edit')" class="underline font-semibold">施設設定</Link>
          から「houkago-plus 事業所ID」とAPIトークンを設定してください。
        </div>
        <div v-else-if="yoyakuApiReachable === false" class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-sm text-rose-700">
          p-yoyaku側と通信できませんでした。事業所ID・APIトークンの設定、またはp-yoyaku側の稼働状況を確認してください。
        </div>

        <div v-if="importErrors.length" class="p-4 bg-rose-50 border border-rose-200 rounded-lg">
          <p class="font-semibold text-rose-800 mb-2">エラーが見つかったため、紐付けは行われませんでした。</p>
          <ul class="text-sm text-rose-700 list-disc list-inside space-y-1">
            <li v-for="(err, i) in importErrors" :key="i">{{ err }}</li>
          </ul>
        </div>

        <div v-if="linkResults.length" class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
          <p class="font-semibold text-gray-900 mb-1">作成結果</p>
          <p class="text-xs text-amber-700 mb-3">
            パスワードはこの画面にのみ一度だけ表示されます。今のうちに保護者へお渡しする分をメモ・印刷してください。
          </p>
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase border-b">
              <tr>
                <th class="py-2 pr-4">児童名</th>
                <th class="py-2 pr-4">結果</th>
                <th class="py-2 pr-4">メールアドレス</th>
                <th class="py-2">仮パスワード</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="r in linkResults" :key="r.child_id">
                <td class="py-2 pr-4">{{ r.child_name }}</td>
                <td class="py-2 pr-4">
                  <span v-if="r.ok" class="text-green-700">成功</span>
                  <span v-else class="text-rose-700">失敗（通信エラー）</span>
                </td>
                <td class="py-2 pr-4 font-mono">{{ r.email ?? '―' }}</td>
                <td class="py-2 font-mono">{{ r.password ?? '（既存アカウント）' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="text-sm text-gray-500">
          連携済みの児童: <strong>{{ linkedCount }}</strong>件 ／ 未連携の児童: <strong>{{ unlinkedChildren.length }}</strong>件
        </p>

        <!-- 新規作成: はぐくむ→p-yoyaku -->
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
          <h3 class="text-lg font-semibold text-gray-900 mb-1">p-yoyakuアカウントを新規作成</h3>
          <p class="text-sm text-gray-500 mb-4">
            はぐくむにすでに登録済みの児童から、p-yoyaku側にログインアカウントを作成して自動で紐付けます。
            （はぐくむが先行導入済み、または両方を同時に導入する場合）
          </p>

          <div v-if="unlinkedChildren.length === 0" class="text-sm text-gray-500">
            未連携の児童はありません。
          </div>
          <template v-else>
            <div class="overflow-x-auto mb-4">
              <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase border-b">
                  <tr>
                    <th class="py-2 pr-4"><input type="checkbox" v-model="allSelected" /></th>
                    <th class="py-2 pr-4">児童名</th>
                    <th class="py-2 pr-4">カナ</th>
                    <th class="py-2">学校</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="c in unlinkedChildren" :key="c.id">
                    <td class="py-2 pr-4"><input type="checkbox" :value="c.id" v-model="selectedIds" /></td>
                    <td class="py-2 pr-4">{{ c.name }}</td>
                    <td class="py-2 pr-4 text-gray-500">{{ c.name_kana ?? '―' }}</td>
                    <td class="py-2">{{ c.school?.name ?? '―' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              @click="submitCreate"
              :disabled="creating || !configured"
              class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600 disabled:opacity-50"
            >
              {{ creating ? '作成中…' : `選択した${selectedIds.length}件のアカウントを作成` }}
            </button>
          </template>
        </div>

        <!-- CSV紐付け: p-yoyaku→はぐくむ、または既存データ同士の突合 -->
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
          <h3 class="text-lg font-semibold text-gray-900 mb-1">既存のp-yoyakuアカウントにCSVで紐付け</h3>
          <p class="text-sm text-gray-500 mb-4">
            p-yoyaku側にすでに登録済みの利用者がいる場合（p-yoyakuが先行導入済み、または両方に別々に登録済みの場合）、
            誤結合を避けるためID/メールを明示指定したCSVで紐付けます。名前だけでの自動突合は行いません。
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="p-3 bg-gray-50 border rounded">
              <p class="text-xs font-semibold text-gray-600 mb-2">はぐくむ側: 未連携の児童（child_id）</p>
              <div class="max-h-48 overflow-y-auto text-sm">
                <div v-for="c in unlinkedChildren" :key="c.id" class="flex justify-between py-0.5">
                  <span class="font-mono text-gray-500">{{ c.id }}</span>
                  <span>{{ c.name }}</span>
                </div>
                <p v-if="unlinkedChildren.length === 0" class="text-gray-400">未連携の児童はありません</p>
              </div>
            </div>
            <div class="p-3 bg-gray-50 border rounded">
              <p class="text-xs font-semibold text-gray-600 mb-2">p-yoyaku側: 利用者一覧（yoyaku_user_id / メール）</p>
              <div class="max-h-48 overflow-y-auto text-sm">
                <div v-for="u in yoyakuUsers" :key="u.id" class="flex justify-between py-0.5 gap-2">
                  <span class="font-mono text-gray-500">{{ u.id }}</span>
                  <span class="truncate">{{ u.name }}</span>
                  <span class="text-gray-400 truncate">{{ u.email }}</span>
                </div>
                <p v-if="yoyakuUsers.length === 0" class="text-gray-400">取得できませんでした</p>
              </div>
            </div>
          </div>

          <p class="text-xs text-gray-500 mb-2">
            CSVの列: <span class="font-mono">child_id</span>（必須）,
            <span class="font-mono">yoyaku_user_id</span> または <span class="font-mono">yoyaku_email</span>（どちらか必須）
          </p>

          <form @submit.prevent="submitCsv">
            <input
              type="file"
              accept=".csv,text/csv"
              class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer focus:outline-none p-2"
              @change="onFileChange"
            />
            <div class="flex items-center justify-end mt-4">
              <button
                type="submit"
                :disabled="uploading || !configured"
                class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600 disabled:opacity-50"
              >
                {{ uploading ? 'アップロード中…' : 'CSVで紐付け' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
