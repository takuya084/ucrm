<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items: Array,
})

const SERVICE_TYPE_LABELS = { houday: '放デイ', jidou: '児発' }
const CATEGORY_LABELS = { addition: '加算', subtraction: '減算' }
const UNIT_TYPE_LABELS = { per_day: '/日', per_time: '/回', per_month: '/月' }

// ローカルで enabled 状態を管理
const settings = ref(props.items.map(item => ({
  ...item,
  is_enabled: item.is_enabled,
})))

// サービス種別でグループ化
const grouped = computed(() => {
  const groups = {}
  settings.value.forEach(item => {
    const key = item.service_type
    if (!groups[key]) groups[key] = { addition: [], subtraction: [] }
    groups[key][item.category].push(item)
  })
  return groups
})

const hasChanges = computed(() =>
  settings.value.some((s, i) => s.is_enabled !== props.items[i].is_enabled)
)

const save = () => {
  router.post(route('billing.settings.service-codes.update'), {
    settings: settings.value.map(s => ({
      service_code_master_id: s.id,
      is_enabled: s.is_enabled,
    })),
  })
}

const enabledCount = computed(() => settings.value.filter(s => s.is_enabled).length)

// --- マスターCSV取込 ---
const showImport = ref(false)
const importFile = ref(null)
const importing = ref(false)
const importErrors = ref([])
const onImportFile = (e) => { importFile.value = e.target.files[0] || null }
const submitImport = () => {
  if (!importFile.value) { alert('CSVファイルを選択してください'); return }
  const fd = new FormData()
  fd.append('file', importFile.value)
  importing.value = true
  importErrors.value = []
  router.post(route('billing.settings.service-codes.import'), fd, {
    forceFormData: true,
    onSuccess: (page) => {
      importErrors.value = page.props.flash?.import_errors ?? []
      importFile.value = null
    },
    onFinish: () => { importing.value = false },
  })
}
</script>

<template>
  <Head title="加算・減算設定" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('billing.index')" class="text-gray-400 hover:text-gray-600 text-sm">
          ← 請求管理
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">加算・減算設定</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />
        <BreezeValidationErrors />

        <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-700 flex items-center justify-between">
          <div>
            事業所で算定する加算・減算にチェックを入れてください。チェックされた項目のみ請求計算で適用されます。
            <br />現在 <strong>{{ enabledCount }}</strong> 件が有効です。
          </div>
          <button @click="showImport = !showImport"
            class="ml-4 px-3 py-1.5 text-xs border border-emerald-500 text-emerald-600 bg-white rounded hover:bg-emerald-50 whitespace-nowrap">
            {{ showImport ? '閉じる' : '📥 マスターCSV取込' }}
          </button>
        </div>

        <div v-if="showImport" class="bg-white shadow-sm rounded-lg p-5 border border-emerald-200">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">サービスコードマスターCSV取込</h3>
          <p class="text-xs text-gray-500 mb-3 leading-relaxed">
            ヘッダ行必須。2行目以降は以下9列（9列目は空可）:<br>
            <code class="bg-gray-100 px-1">報酬改定日, サービス種別(houday|jidou|63|61), サービスコード, サービス内容名, 単位数, 単位種別(per_day|per_time|per_month), 区分(base|addition|subtraction|1|2|3), 有効開始日, 有効終了日</code><br>
            ※ (service_code, valid_from) が一致するレコードは上書き更新。それ以外は新規登録。
          </p>
          <div class="flex items-center gap-3">
            <input type="file" accept=".csv,.txt" @change="onImportFile" class="text-sm" />
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

        <div v-for="(categories, serviceType) in grouped" :key="serviceType"
          class="bg-white shadow-sm sm:rounded-lg overflow-hidden">

          <div class="p-4 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-800">
              {{ SERVICE_TYPE_LABELS[serviceType] ?? serviceType }}
            </h3>
          </div>

          <!-- 加算 -->
          <div v-if="categories.addition.length > 0">
            <div class="px-4 py-2 bg-green-50 border-b text-xs font-medium text-green-700">加算</div>
            <div class="divide-y">
              <label
                v-for="item in categories.addition"
                :key="item.id"
                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer"
              >
                <input
                  v-model="item.is_enabled"
                  type="checkbox"
                  class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-300"
                />
                <div class="flex-1 min-w-0">
                  <div class="text-sm text-gray-800">{{ item.service_name }}</div>
                  <div class="text-xs text-gray-400">{{ item.service_code }}</div>
                </div>
                <div class="text-sm font-medium text-gray-600 whitespace-nowrap">
                  {{ item.unit_count }} 単位{{ UNIT_TYPE_LABELS[item.unit_type] ?? '' }}
                </div>
              </label>
            </div>
          </div>

          <!-- 減算 -->
          <div v-if="categories.subtraction.length > 0">
            <div class="px-4 py-2 bg-red-50 border-b text-xs font-medium text-red-700">減算</div>
            <div class="divide-y">
              <label
                v-for="item in categories.subtraction"
                :key="item.id"
                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer"
              >
                <input
                  v-model="item.is_enabled"
                  type="checkbox"
                  class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-300"
                />
                <div class="flex-1 min-w-0">
                  <div class="text-sm text-gray-800">{{ item.service_name }}</div>
                  <div class="text-xs text-gray-400">{{ item.service_code }}</div>
                </div>
                <div class="text-sm font-medium text-red-600 whitespace-nowrap">
                  {{ item.unit_count }} 単位{{ UNIT_TYPE_LABELS[item.unit_type] ?? '' }}
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- 保存ボタン -->
        <div class="flex justify-end">
          <button
            @click="save"
            :disabled="!hasChanges"
            class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600 disabled:opacity-40"
          >
            設定を保存
          </button>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
