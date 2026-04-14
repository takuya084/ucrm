<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  returns:      Object,
  children:     Array,
  stats:        Object,
  filters:      Object,
  statusLabels: Object,
  codePresets:  Object,
})

const fmt = (n) => Number(n ?? 0).toLocaleString()

const STATUS_COLOR = {
  returned:     'bg-red-100 text-red-700',
  resubmitting: 'bg-yellow-100 text-yellow-700',
  resubmitted:  'bg-blue-100 text-blue-700',
  resolved:     'bg-green-100 text-green-700',
}

// --- フィルタ ---
const filters = reactive({
  month:       props.filters.month       ?? '',
  status:      props.filters.status      ?? '',
  child_id:    props.filters.child_id    ?? '',
  return_code: props.filters.return_code ?? '',
})
const applyFilter = () => {
  Inertia.get(route('billing.returns.index'), filters, { preserveState: true, replace: true })
}
const resetFilter = () => {
  filters.month = ''; filters.status = ''; filters.child_id = ''; filters.return_code = ''
  applyFilter()
}

// --- 新規登録 ---
const showForm = ref(false)
const form = reactive({
  year_month: '', child_id: '', return_code: '', return_reason: '',
  original_amount: 0, received_at: new Date().toISOString().slice(0, 10),
  remarks: '',
})
const applyPreset = (code) => {
  form.return_code = code
  if (code !== 'OTHER') form.return_reason = props.codePresets[code]
}
const submitReturn = () => {
  Inertia.post(route('billing.returns.store'), form, {
    onSuccess: () => { showForm.value = false }
  })
}

// --- 編集 ---
const editing = ref(null)
const editForm = reactive({ return_code: '', return_reason: '', remarks: '', received_at: '' })
const startEdit = (ret) => {
  editing.value = ret.id
  editForm.return_code   = ret.return_code ?? ''
  editForm.return_reason = ret.return_reason ?? ''
  editForm.remarks       = ret.remarks ?? ''
  editForm.received_at   = ret.received_at ?? ''
}
const cancelEdit = () => { editing.value = null }
const saveEdit = (id) => {
  Inertia.patch(route('billing.returns.update', id), editForm, {
    onSuccess: () => { editing.value = null }
  })
}

// --- CSVインポート ---
const showImport = ref(false)
const importFile = ref(null)
const importing = ref(false)
const importErrors = ref([])
const onFileChange = (e) => { importFile.value = e.target.files[0] || null }
const submitImport = () => {
  if (!importFile.value) { alert('CSVファイルを選択してください'); return }
  const fd = new FormData()
  fd.append('file', importFile.value)
  importing.value = true
  importErrors.value = []
  Inertia.post(route('billing.returns.import'), fd, {
    forceFormData: true,
    onSuccess: (page) => {
      importErrors.value = page.props.flash?.import_errors ?? []
      importFile.value = null
    },
    onFinish: () => { importing.value = false },
  })
}

// --- 状態遷移 ---
const transition = (id, action, label) => {
  if (!confirm(`「${label}」に遷移しますか？`)) return
  Inertia.post(route('billing.returns.transition', id), { action })
}
</script>

<template>
  <Head title="返戻管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">返戻管理</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
          <button @click="showImport = !showImport" class="px-4 py-1.5 text-xs border border-emerald-500 text-emerald-600 rounded hover:bg-emerald-50">
            {{ showImport ? '閉じる' : '📥 CSV取込' }}
          </button>
          <button @click="showForm = !showForm" class="px-4 py-1.5 text-xs bg-indigo-500 text-white rounded hover:bg-indigo-600">
            {{ showForm ? '閉じる' : '＋ 返戻登録' }}
          </button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- サマリー -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div class="bg-red-50 rounded-lg p-4">
            <div class="text-xs text-red-600">未対応（返戻）</div>
            <div class="text-2xl font-bold text-red-700 mt-1">{{ stats.returned }}</div>
          </div>
          <div class="bg-yellow-50 rounded-lg p-4">
            <div class="text-xs text-yellow-700">再請求準備中</div>
            <div class="text-2xl font-bold text-yellow-700 mt-1">{{ stats.resubmitting }}</div>
          </div>
          <div class="bg-blue-50 rounded-lg p-4">
            <div class="text-xs text-blue-600">再請求済</div>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ stats.resubmitted }}</div>
          </div>
          <div class="bg-green-50 rounded-lg p-4">
            <div class="text-xs text-green-600">解決済</div>
            <div class="text-2xl font-bold text-green-700 mt-1">{{ stats.resolved }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-xs text-gray-500">累計返戻額</div>
            <div class="text-2xl font-bold text-gray-700 mt-1">{{ fmt(stats.total_amount) }}<span class="text-sm">円</span></div>
          </div>
        </div>

        <!-- フィルタ -->
        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">年月</label>
            <input v-model="filters.month" type="month" class="border border-gray-300 rounded px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">ステータス</label>
            <select v-model="filters.status" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="(l, k) in statusLabels" :key="k" :value="k">{{ l }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">児童</label>
            <select v-model="filters.child_id" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">返戻コード</label>
            <select v-model="filters.return_code" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
              <option value="">すべて</option>
              <option v-for="(l, k) in codePresets" :key="k" :value="k">{{ k }} - {{ l }}</option>
            </select>
          </div>
          <button @click="applyFilter" class="px-4 py-1.5 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">適用</button>
          <button @click="resetFilter" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">リセット</button>
        </div>

        <!-- CSVインポート -->
        <div v-if="showImport" class="bg-white shadow-sm rounded-lg p-5 border border-emerald-200">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">国保連返戻CSV取込</h3>
          <p class="text-xs text-gray-500 mb-3 leading-relaxed">
            1行目はヘッダ行。2行目以降に以下の6列（カンマ区切り）：<br>
            <code class="bg-gray-100 px-1">受給者証番号, サービス提供年月(YYYYMM or YYYY-MM), 返戻コード, 返戻理由, 費用額, 受領日(YYYY-MM-DD or YYYYMMDD)</code><br>
            ※ Shift_JIS / UTF-8 いずれも自動判定。同一児童・同月・同コードの重複はスキップ。
          </p>
          <div class="flex items-center gap-3">
            <input type="file" accept=".csv,.txt" @change="onFileChange" class="text-sm" />
            <button @click="submitImport" :disabled="importing || !importFile"
              class="px-4 py-1.5 text-sm bg-emerald-600 text-white rounded hover:bg-emerald-700 disabled:opacity-50">
              {{ importing ? '取込中...' : '取込実行' }}
            </button>
          </div>
          <div v-if="importErrors.length" class="mt-3 bg-red-50 border border-red-200 rounded p-3 max-h-48 overflow-y-auto">
            <div class="text-xs font-semibold text-red-700 mb-1">取込エラー ({{ importErrors.length }}件)</div>
            <ul class="text-xs text-red-700 space-y-0.5">
              <li v-for="(e, i) in importErrors" :key="i">• {{ e }}</li>
            </ul>
          </div>
        </div>

        <!-- 返戻登録フォーム -->
        <div v-if="showForm" class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">返戻登録</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">対象年月</label>
              <input v-model="form.year_month" type="month" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">児童</label>
              <select v-model="form.child_id" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="">選択してください</option>
                <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">返戻コード</label>
              <select :value="form.return_code" @change="applyPreset($event.target.value)"
                class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="">選択してください</option>
                <option v-for="(l, k) in codePresets" :key="k" :value="k">{{ k }} - {{ l }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">元請求額</label>
              <input v-model.number="form.original_amount" type="number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">受領日</label>
              <input v-model="form.received_at" type="date" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs text-gray-500 mb-1">返戻理由</label>
              <textarea v-model="form.return_reason" rows="2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"></textarea>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs text-gray-500 mb-1">備考</label>
              <textarea v-model="form.remarks" rows="2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"></textarea>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <button @click="submitReturn" class="px-6 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">登録</button>
          </div>
        </div>

        <!-- 返戻一覧 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="returns.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
            返戻データがありません
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-3 py-2 text-left">児童</th>
                <th class="px-3 py-2 text-left">年月</th>
                <th class="px-3 py-2 text-left">コード</th>
                <th class="px-3 py-2 text-right">元請求額</th>
                <th class="px-3 py-2 text-left">ステータス</th>
                <th class="px-3 py-2 text-left">受領</th>
                <th class="px-3 py-2 text-left">再請求</th>
                <th class="px-3 py-2 text-left">解決</th>
                <th class="px-3 py-2 text-left">理由/備考</th>
                <th class="px-3 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <template v-for="ret in returns.data" :key="ret.id">
                <tr class="hover:bg-gray-50">
                  <td class="px-3 py-2">
                    <div class="font-medium">{{ ret.child?.name }}</div>
                    <div v-if="ret.child?.name_kana" class="text-xs text-gray-400">{{ ret.child.name_kana }}</div>
                  </td>
                  <td class="px-3 py-2 text-xs">{{ ret.year_month }}</td>
                  <td class="px-3 py-2 text-xs font-mono">{{ ret.return_code ?? '—' }}</td>
                  <td class="px-3 py-2 text-right">{{ fmt(ret.original_amount) }}円</td>
                  <td class="px-3 py-2">
                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[ret.status]]">
                      {{ statusLabels[ret.status] }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-500">{{ ret.received_at ?? '—' }}</td>
                  <td class="px-3 py-2 text-xs text-gray-500">{{ ret.resubmitted_at ?? '—' }}</td>
                  <td class="px-3 py-2 text-xs text-gray-500">{{ ret.resolved_at ?? '—' }}</td>
                  <td class="px-3 py-2 text-xs text-gray-500 max-w-[220px]">
                    <div class="truncate" :title="ret.return_reason">{{ ret.return_reason ?? '—' }}</div>
                    <div v-if="ret.remarks" class="truncate text-gray-400" :title="ret.remarks">📝 {{ ret.remarks }}</div>
                  </td>
                  <td class="px-3 py-2 text-right whitespace-nowrap">
                    <button @click="startEdit(ret)" class="text-xs px-2 py-0.5 border border-gray-300 rounded hover:bg-gray-50 text-gray-600 mr-1">編集</button>
                    <button v-if="ret.status === 'returned'" @click="transition(ret.id, 'start_resubmit', '再請求準備中')"
                      class="text-xs px-2 py-0.5 bg-yellow-500 text-white rounded hover:bg-yellow-600">準備</button>
                    <button v-else-if="ret.status === 'resubmitting'" @click="transition(ret.id, 'mark_resubmitted', '再請求済')"
                      class="text-xs px-2 py-0.5 bg-blue-500 text-white rounded hover:bg-blue-600">再請求済</button>
                    <button v-else-if="ret.status === 'resubmitted'" @click="transition(ret.id, 'mark_resolved', '解決済')"
                      class="text-xs px-2 py-0.5 bg-green-500 text-white rounded hover:bg-green-600">解決</button>
                    <button v-if="['resubmitting','resubmitted','resolved'].includes(ret.status)"
                      @click="transition(ret.id, 'revert', '前の状態')"
                      class="text-xs px-2 py-0.5 border border-gray-300 rounded hover:bg-gray-50 text-gray-500 ml-1">↶</button>
                  </td>
                </tr>
                <tr v-if="editing === ret.id" class="bg-indigo-50">
                  <td colspan="10" class="px-4 py-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">返戻コード</label>
                        <select v-model="editForm.return_code" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                          <option value="">—</option>
                          <option v-for="(l, k) in codePresets" :key="k" :value="k">{{ k }} - {{ l }}</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">受領日</label>
                        <input v-model="editForm.received_at" type="date" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" />
                      </div>
                      <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">返戻理由</label>
                        <textarea v-model="editForm.return_reason" rows="2" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></textarea>
                      </div>
                      <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">備考</label>
                        <textarea v-model="editForm.remarks" rows="2" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></textarea>
                      </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                      <button @click="cancelEdit" class="px-4 py-1 text-xs border border-gray-300 rounded hover:bg-gray-50">取消</button>
                      <button @click="saveEdit(ret.id)" class="px-4 py-1 text-xs bg-indigo-500 text-white rounded hover:bg-indigo-600">保存</button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>

          <div v-if="returns.last_page > 1" class="px-5 py-3 border-t flex gap-2 text-sm">
            <Link v-for="link in returns.links" :key="link.label" :href="link.url ?? '#'" v-html="link.label"
              :class="['px-3 py-1 border rounded', link.active ? 'bg-indigo-500 text-white border-indigo-500' : 'border-gray-300 text-gray-600 hover:bg-gray-50', !link.url ? 'opacity-40 pointer-events-none' : '']" />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
