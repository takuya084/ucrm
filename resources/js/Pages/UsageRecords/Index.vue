<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import QuickNav from '@/Components/QuickNav.vue'
import { ref, computed, watch } from 'vue'
import { Inertia } from '@inertiajs/inertia'
import axios from 'axios'

const props = defineProps({
  date:              String,
  dayName:           String,
  rows:              Array,
  dataSource:        String, // 'yoyaku' | 'schedule' | 'records'
  hasRecords:        Boolean,
  availableChildren: Array,
  serverTs:          Number, // サーバーが毎回発行するタイムスタンプ（prop変更検知用）
})

const LOCATION_LABELS = { school: '学校', home: '自宅', parents: '親送迎', others: 'その他' }
const locationLabel = (loc) => LOCATION_LABELS[loc] ?? loc

// 日付ナビゲーション
const selectedDate = ref(props.date)
const goToDate = () => {
  Inertia.get(route('usage-records.index'), { date: selectedDate.value }, { preserveState: false })
}

// 各行の記録を ref で管理
const records = ref(props.rows.map(r => ({ ...r })))

// serverTs が変わる＝サーバーから新しいデータが届いた → records を最新propsで上書き
watch(() => props.serverTs, () => {
  records.value = props.rows.map(r => ({ ...r }))
  selectedDate.value = props.date
})

// 追加パネルの表示制御
const showAddPanel = ref(false)
const selectedChildId = ref('')

// 現在リストにいる child_id の集合（追加候補のフィルタリング用）
const currentChildIds = computed(() => new Set(records.value.map(r => r.child_id)))

// サーバーから来た availableChildren のうち、まだリストにいないもの
const availableToAdd = computed(() =>
  props.availableChildren.filter(c => !currentChildIds.value.has(c.id))
)

// ── 自動保存 ─────────────────────────────────────────────
const saveStatus = ref('')   // '' | 'saving' | 'saved' | 'error'
let saveTimer = null

const autoSave = () => {
  saveStatus.value = 'saving'
  clearTimeout(saveTimer)

  axios.post(route('usage-records.bulk-store'), {
    date:    props.date,
    records: records.value.map(r => ({
      child_id:       r.child_id,
      status:         r.status,
      absent_reason:  r.absent_reason,
      pickup_done:    r.pickup_done,
      dropoff_done:   r.dropoff_done,
      billing_target: r.billing_target,
      memo:           r.memo,
    })),
  }).then(res => {
    // 返ってきた usage_record_id をローカルに反映（支援記録ボタン表示用）
    const ids = res.data.ids ?? {}
    records.value.forEach(r => {
      if (ids[r.child_id]) r.usage_record_id = ids[r.child_id]
    })
    saveStatus.value = 'saved'
    saveTimer = setTimeout(() => { saveStatus.value = '' }, 1500)
  }).catch(() => {
    saveStatus.value = 'error'
    saveTimer = setTimeout(() => { saveStatus.value = '' }, 3000)
  })
}

// 手動保存（フォールバック）
const save = () => autoSave()

// 行を削除
const removeRow = (row) => {
  // 保存済みの場合は確認ダイアログ
  if (row.usage_record_id) {
    if (!confirm(`${row.child_name} をこの日のリストから削除しますか？\n（出席記録がデータベースから削除されます）`)) {
      return
    }
  }
  const idx = records.value.findIndex(r => r.child_id === row.child_id)
  if (idx !== -1) {
    records.value.splice(idx, 1)
    autoSave()
  }
}

// 児童を追加
const addChild = () => {
  const id    = Number(selectedChildId.value)
  const child = props.availableChildren.find(c => c.id === id)
  if (!child) return

  records.value.push({
    child_id:                child.id,
    child_name:              child.name,
    child_name_kana:         child.name_kana,
    school_name:             child.school?.name ?? null,
    pickup_required:         child.pickup_required ?? false,
    yoyaku_pickup_time:      null,
    yoyaku_dropoff_time:     null,
    yoyaku_pickup_location:  null,
    yoyaku_dropoff_location: null,
    usage_record_id:         null,
    status:                  'attended',
    absent_reason:           '',
    pickup_done:             child.pickup_required ?? false,
    dropoff_done:            child.pickup_required ?? false,
    billing_target:          true,
    memo:                    '',
    has_support_record:      false,
    support_record_id:       null,
  })

  selectedChildId.value = ''
  showAddPanel.value = false
  autoSave()
}

// 集計
const summary = computed(() => ({
  attended:    records.value.filter(r => r.status === 'attended').length,
  absent:      records.value.filter(r => r.status !== 'attended').length,
  total:       records.value.length,
  withSupport: records.value.filter(r => r.has_support_record).length,
}))

const STATUS_OPTIONS = [
  { value: 'attended',      label: '出席',           color: 'text-green-700 bg-green-50 border-green-200' },
  { value: 'absent_notice', label: '欠席（連絡あり）', color: 'text-yellow-700 bg-yellow-50 border-yellow-200' },
  { value: 'absent',        label: '無断欠席',         color: 'text-red-700 bg-red-50 border-red-200' },
  { value: 'cancel',        label: 'キャンセル',        color: 'text-gray-600 bg-gray-50 border-gray-200' },
]

// 出席者を上、欠席・キャンセルを下に並べる
const sortedRecords = computed(() =>
  [...records.value].sort((a, b) => {
    const aIsAttended = a.status === 'attended' ? 0 : 1
    const bIsAttended = b.status === 'attended' ? 0 : 1
    return aIsAttended - bIsAttended
  })
)

// ステータス変更（支援記録がある児童を出席以外にする場合は確認）→ 自動保存
const changeStatus = (row, newStatus) => {
  if (newStatus === row.status) return
  if (row.has_support_record && row.status === 'attended' && newStatus !== 'attended') {
    if (!confirm('この児童には支援記録があります。\nステータスを変更してもよいですか？\n（支援記録は保持されます。出席に戻せば元通りになります）')) {
      return
    }
  }
  row.status = newStatus
  autoSave()
}

const rowBg = (status) => ({
  attended:      'bg-white',
  absent_notice: 'bg-yellow-50',
  absent:        'bg-red-50',
  cancel:        'bg-gray-50',
})[status] ?? 'bg-white'
</script>

<template>
  <Head title="出席管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 flex-wrap">
        <h2 class="font-semibold text-xl text-gray-800">出席管理</h2>

        <!-- データソースバッジ -->
        <span v-if="dataSource === 'yoyaku'"
          class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full font-medium">
          送迎予約システム連携中
        </span>
        <span v-else-if="dataSource === 'records'"
          class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full font-medium">
          保存済み
        </span>
        <span v-else
          class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">
          固定スケジュール
        </span>

        <!-- 日付ナビゲーション -->
        <div class="flex items-center gap-2">
          <button
            @click="selectedDate = new Date(new Date(selectedDate).getTime() - 86400000).toISOString().slice(0,10); goToDate()"
            class="px-2 py-1 border rounded text-sm hover:bg-gray-100"
          >◀</button>
          <input
            v-model="selectedDate"
            type="date"
            class="border border-gray-300 rounded px-2 py-1 text-sm"
            @change="goToDate"
          />
          <span class="text-sm font-medium text-indigo-600">（{{ dayName }}曜日）</span>
          <button
            @click="selectedDate = new Date(new Date(selectedDate).getTime() + 86400000).toISOString().slice(0,10); goToDate()"
            class="px-2 py-1 border rounded text-sm hover:bg-gray-100"
          >▶</button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <QuickNav />
        <FlashMessage />

        <!-- 集計バー -->
        <div class="grid grid-cols-4 gap-3">
          <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-gray-800">{{ summary.total }}</div>
            <div class="text-xs text-gray-500 mt-1">本日予定</div>
          </div>
          <div class="bg-green-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-green-700">{{ summary.attended }}</div>
            <div class="text-xs text-gray-500 mt-1">出席</div>
          </div>
          <div class="bg-yellow-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-yellow-700">{{ summary.absent }}</div>
            <div class="text-xs text-gray-500 mt-1">欠席</div>
          </div>
          <div class="bg-indigo-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-indigo-700">{{ summary.withSupport }}</div>
            <div class="text-xs text-gray-500 mt-1">支援記録済</div>
          </div>
        </div>

        <!-- 出席一覧 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-medium text-gray-700">{{ date.slice(0, 10) }} の出席記録</h3>
            <span v-if="saveStatus === 'saving'" class="text-xs text-gray-400">保存中...</span>
            <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-600 font-medium">✓ 保存完了</span>
            <span v-else-if="saveStatus === 'error'" class="text-xs text-red-600 font-medium">保存失敗</span>
            <span v-else class="text-xs text-gray-300">自動保存</span>
          </div>

          <!-- テンプレートモードの注記 + 初回保存ボタン -->
          <div v-if="!hasRecords && records.length > 0"
            class="px-4 py-2 bg-blue-50 border-b text-xs text-blue-600 flex items-center justify-between gap-2">
            <span>固定スケジュールから読み込みました。ステータス変更・児童の追加削除は自動保存されます。</span>
            <button @click="autoSave" class="px-4 py-1.5 bg-indigo-500 text-white text-xs rounded hover:bg-indigo-600 whitespace-nowrap">
              このメンバーで確定
            </button>
          </div>

          <div v-if="records.length === 0" class="py-12 text-center text-gray-400">
            <p>この日の利用予定児童が登録されていません</p>
            <p class="text-xs mt-1">「＋ 児童を追加」から手動で追加できます</p>
          </div>

          <div v-else class="divide-y">
            <div
              v-for="row in sortedRecords"
              :key="row.child_id"
              :class="['p-4 transition-colors', rowBg(row.status)]"
            >
              <!-- 上段：名前 + ステータス + 削除ボタン -->
              <div class="flex items-center gap-3 flex-wrap">
                <!-- 児童名 -->
                <div class="w-36">
                  <Link :href="route('children.show', row.child_id)"
                    class="font-medium text-gray-900 hover:text-indigo-600 text-sm">
                    {{ row.child_name }}
                  </Link>
                  <div class="text-xs text-gray-400">{{ row.school_name ?? '学校未登録' }}</div>
                  <div v-if="row.allergy_note"
                    class="mt-0.5 px-1.5 py-0.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded">
                    ⚠ {{ row.allergy_note }}
                  </div>
                </div>

                <!-- ステータス選択 -->
                <div class="flex gap-1 flex-wrap">
                  <button
                    v-for="opt in STATUS_OPTIONS"
                    :key="opt.value"
                    type="button"
                    @click="changeStatus(row, opt.value)"
                    :class="[
                      'px-3 py-1.5 text-xs border rounded-full font-medium transition-all',
                      row.status === opt.value ? opt.color : 'border-gray-200 text-gray-400 bg-white hover:bg-gray-50'
                    ]"
                  >{{ opt.label }}</button>
                </div>

                <!-- 右端：支援記録ボタン + 削除ボタン -->
                <div class="ml-auto flex items-center gap-2">
                  <Link
                    v-if="row.has_support_record"
                    :href="route('support-records.show', row.support_record_id)"
                    class="text-xs px-3 py-1.5 border border-indigo-300 text-indigo-600 rounded hover:bg-indigo-50"
                  >記録を見る</Link>
                  <Link
                    v-else-if="row.usage_record_id && row.status === 'attended'"
                    :href="route('support-records.create', { usage_record_id: row.usage_record_id })"
                    class="text-xs px-3 py-1.5 bg-green-500 text-white rounded hover:bg-green-600"
                  >支援記録を入力</Link>

                  <!-- 削除ボタン -->
                  <button
                    v-if="!row.has_support_record"
                    type="button"
                    @click="removeRow(row)"
                    class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors"
                    title="この日のリストから削除"
                  >×</button>
                  <span
                    v-else
                    class="w-7 h-7 flex items-center justify-center rounded-full text-gray-200 cursor-not-allowed"
                    title="支援記録があるため削除できません（キャンセルに変更してください）"
                  >×</span>
                </div>
              </div>

              <!-- 欠席理由 -->
              <div v-if="row.status !== 'attended'" class="mt-2 flex gap-3 items-center">
                <input
                  v-model="row.absent_reason"
                  type="text"
                  placeholder="欠席理由（例：体調不良）"
                  class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs"
                />
              </div>

              <!-- 送迎チェック（出席時のみ） -->
              <div v-if="row.status === 'attended' && row.pickup_required" class="mt-2 space-y-1">
                <!-- 送迎予約情報（houkago-plus連携時のみ） -->
                <div v-if="row.yoyaku_pickup_time || row.yoyaku_dropoff_time"
                  class="flex gap-4 text-xs text-blue-600 bg-blue-50 rounded px-2 py-1">
                  <span v-if="row.yoyaku_pickup_time">
                    迎え {{ row.yoyaku_pickup_time }}
                    <span v-if="row.yoyaku_pickup_location">／{{ locationLabel(row.yoyaku_pickup_location) }}</span>
                  </span>
                  <span v-if="row.yoyaku_dropoff_time">
                    送り {{ row.yoyaku_dropoff_time }}
                    <span v-if="row.yoyaku_dropoff_location">／{{ locationLabel(row.yoyaku_dropoff_location) }}</span>
                  </span>
                </div>
                <div class="flex gap-4 text-xs text-gray-600">
                  <label class="flex items-center gap-1 cursor-pointer">
                    <input v-model="row.pickup_done" type="checkbox" class="w-3 h-3" />迎え完了
                  </label>
                  <label class="flex items-center gap-1 cursor-pointer">
                    <input v-model="row.dropoff_done" type="checkbox" class="w-3 h-3" />送り完了
                  </label>
                  <label class="flex items-center gap-1 cursor-pointer">
                    <input v-model="row.billing_target" type="checkbox" class="w-3 h-3" />請求対象
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- 児童追加パネル -->
          <div class="p-4 border-t bg-gray-50">
            <div v-if="!showAddPanel" class="flex items-center justify-between">
              <button
                v-if="availableToAdd.length > 0"
                type="button"
                @click="showAddPanel = true"
                class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
              >
                <span class="text-lg leading-none">＋</span> 児童を追加
              </button>
              <span v-else class="text-xs text-gray-400">追加できる児童はいません</span>

              <span v-if="saveStatus === 'saving'" class="text-xs text-gray-400">保存中...</span>
              <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-600 font-medium">✓ 保存完了</span>
              <span v-else-if="saveStatus === 'error'" class="text-xs text-red-600 font-medium">保存失敗</span>
            </div>

            <div v-else class="flex items-center gap-3 flex-wrap">
              <select
                v-model="selectedChildId"
                class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
              >
                <option value="">追加する児童を選択...</option>
                <option v-for="c in availableToAdd" :key="c.id" :value="c.id">
                  {{ c.name }}（{{ c.grade ?? '学年未登録' }}）
                </option>
              </select>
              <button
                type="button"
                @click="addChild"
                :disabled="!selectedChildId"
                class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 disabled:opacity-40"
              >追加</button>
              <button
                type="button"
                @click="showAddPanel = false; selectedChildId = ''"
                class="px-4 py-2 text-sm text-gray-500 border border-gray-300 rounded hover:bg-gray-100"
              >キャンセル</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
