<script setup>
import MenuDestination from '@/Components/MenuDestination.vue'
import DisclosurePanel from '@/Components/DisclosurePanel.vue'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import ContactNoteStatus from '@/Components/ContactNoteStatus.vue'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
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

// Use explicit options: native time pickers do not consistently show step=300.
const timeSlots = Array.from({ length: 24 * 12 }, (_, index) =>
  `${String(Math.floor(index / 12)).padStart(2, '0')}:${String((index % 12) * 5).padStart(2, '0')}`
)

// 日付ナビゲーション
const selectedDate = ref(props.date)
const goToDate = () => {
  router.get(route('usage-records.index'), { date: selectedDate.value }, { preserveState: false })
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
let saveInFlight = false
let saveRequested = false

// Keep a single request in flight. Rapid edits are coalesced into the latest
// full roster after the preceding write, so an older save cannot finish last.
const autoSave = async () => {
  saveRequested = true
  saveStatus.value = 'saving'
  clearTimeout(saveTimer)
  if (saveInFlight) return
  saveInFlight = true
  try {
    while (saveRequested) {
      saveRequested = false
      const res = await axios.post(route('usage-records.bulk-store'), {
        date:    props.date,
        records: records.value.map(r => ({
          child_id:       r.child_id,
          status:         r.status,
          absent_reason:  r.absent_reason,
          pickup_done:    r.pickup_done,
          dropoff_done:   r.dropoff_done,
          billing_target: r.billing_target,
          memo:           r.memo,
          check_in_time:  r.check_in_time || null,
          check_out_time: r.check_out_time || null,
          is_school_day:  r.is_school_day ?? true,
          service_type:   r.service_type || null,
        })),
      })
      const ids = res.data.ids ?? {}
      records.value.forEach(r => {
        if (ids[r.child_id]) r.usage_record_id = ids[r.child_id]
      })
    }
    saveStatus.value = 'saved'
    saveTimer = setTimeout(() => { saveStatus.value = '' }, 1500)
  } catch {
    // Keep the edited values on screen and require an explicit retry.
    saveRequested = false
    saveStatus.value = 'error'
  } finally {
    saveInFlight = false
  }
}

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
    check_in_time:           '',
    check_out_time:          '',
    is_school_day:           true,
    service_type:            '',
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
  { value: 'absent_notice', label: '欠席（連絡あり）', color: 'text-amber-700 bg-amber-50 border-amber-200' },
  { value: 'absent',        label: '無断欠席',         color: 'text-red-700 bg-red-50 border-red-200' },
  { value: 'cancel',        label: 'キャンセル',        color: 'text-gray-600 bg-gray-50 border-gray-200' },
]

// Keep the server's child order during editing. Moving a row after a status
// change puts another child's button under the pointer on the next click.

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

</script>

<template>
  <Head title="出席管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 flex-wrap">
        <h2 class="font-semibold text-xl text-gray-800">出席管理</h2>
        <MenuDestination label="欠席連絡・相談記録" :href="route('absences.index', {date})" />

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
          class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded-full">
          固定スケジュール
        </span>

        <!-- 日付ナビゲーション -->
        <div class="flex items-center gap-2">
          <button
            @click="selectedDate = new Date(new Date(selectedDate).getTime() - 86400000).toISOString().slice(0,10); goToDate()"
            class="px-2 py-1 border rounded-md text-sm hover:bg-gray-100"
          >◀</button>
          <input
            v-model="selectedDate"
            type="date"
            class="border border-gray-300 rounded-md px-2 py-1 text-sm"
            @change="goToDate"
          />
          <span class="text-sm font-medium text-primary-600">（{{ dayName }}曜日）</span>
          <button
            @click="selectedDate = new Date(new Date(selectedDate).getTime() + 86400000).toISOString().slice(0,10); goToDate()"
            class="px-2 py-1 border rounded-md text-sm hover:bg-gray-100"
          >▶</button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">出欠・利用実績・送迎を記録します。家庭からの連絡や連絡帳は、児童ごとのリンクから確認できます。</p>
        <FlashMessage />

        <!-- 集計バー -->
        <div class="grid grid-cols-4 gap-3">
          <div class="bg-white rounded-lg border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold text-gray-800">{{ summary.total }}</div>
            <div class="text-xs text-gray-500 mt-1">本日予定</div>
          </div>
          <div class="bg-green-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-green-700">{{ summary.attended }}</div>
            <div class="text-xs text-gray-500 mt-1">出席</div>
          </div>
          <div class="bg-amber-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-amber-700">{{ summary.absent }}</div>
            <div class="text-xs text-gray-500 mt-1">欠席</div>
          </div>
          <div class="bg-primary-50 rounded-lg shadow-sm p-4 text-center">
            <div class="text-2xl font-bold text-primary-700">{{ summary.withSupport }}</div>
            <div class="text-xs text-gray-500 mt-1">支援記録済</div>
          </div>
        </div>

        <!-- 出席一覧 -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
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
            <button @click="autoSave" class="px-4 py-1.5 bg-primary-500 text-white text-xs rounded-md hover:bg-primary-600 whitespace-nowrap">
              このメンバーで確定
            </button>
          </div>

          <div v-if="records.length === 0" class="py-12 text-center text-gray-400">
            <p>この日の利用予定児童が登録されていません</p>
            <p class="text-xs mt-1">「＋ 児童を追加」から手動で追加できます</p>
          </div>

          <div v-else class="attendance-entries">
            <article v-for="row in records" :key="row.child_id" class="attendance-entry" :data-attendance-status="row.status" :aria-label="row.child_name + 'の出席記録'">
              <header class="attendance-entry-header">
                <div class="attendance-person">
                  <span class="workspace-avatar" aria-hidden="true">{{ row.child_name.replace('【架空】', '').trim().slice(0, 1) }}</span>
                  <div class="min-w-0">
                    <Link :href="route('children.show', row.child_id)" class="font-semibold text-gray-900 hover:text-primary-600">{{ row.child_name }}</Link>
                    <p class="text-xs text-gray-500">{{ row.school_name ?? '学校未登録' }}</p>
                  </div>
                </div>
                <div class="attendance-status-choices" role="group" :aria-label="row.child_name + 'の出欠'">
                  <button v-for="opt in STATUS_OPTIONS" :key="opt.value" type="button" @click="changeStatus(row, opt.value)"
                    :aria-pressed="row.status === opt.value" :class="['rounded-full border px-3 py-2 text-sm font-semibold transition-colors', row.status === opt.value ? opt.color : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50']">{{ opt.label }}</button>
                </div>
                <button v-if="!row.has_support_record" type="button" @click="removeRow(row)" class="attendance-remove" title="この日のリストから削除" :aria-label="row.child_name + 'をこの日のリストから削除'">×</button>
                <span v-else class="attendance-remove opacity-30" title="支援記録があるため削除できません（キャンセルに変更してください）" aria-hidden="true">×</span>
              </header>

              <div v-if="row.status !== 'attended'" class="p-4">
                <label class="block text-sm text-gray-700">欠席理由
                  <input v-model="row.absent_reason" type="text" placeholder="欠席理由（例：体調不良）" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm" @change="autoSave" />
                </label>
              </div>
              <div v-else class="attendance-entry-fields">
                <div class="attendance-field-group" role="group" :aria-label="row.child_name + 'の利用時間'">
                  <h4 class="attendance-field-title">利用時間</h4>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm text-gray-600">来所
                      <select v-model="row.check_in_time" :aria-label="`${row.child_name}の来所時刻`" class="mt-1 block w-full rounded-md border-gray-300 text-base tabular-nums" @change="autoSave">
                        <option value="">未設定</option>
                        <option v-if="row.check_in_time && !timeSlots.includes(row.check_in_time)" :value="row.check_in_time">{{ row.check_in_time }}（既存値）</option>
                        <option v-for="time in timeSlots" :key="time" :value="time">{{ time }}</option>
                      </select>
                    </label>
                    <label class="text-sm text-gray-600">退所
                      <select v-model="row.check_out_time" :aria-label="`${row.child_name}の退所時刻`" class="mt-1 block w-full rounded-md border-gray-300 text-base tabular-nums" @change="autoSave">
                        <option value="">未設定</option>
                        <option v-if="row.check_out_time && !timeSlots.includes(row.check_out_time)" :value="row.check_out_time">{{ row.check_out_time }}（既存値）</option>
                        <option v-for="time in timeSlots" :key="time" :value="time">{{ time }}</option>
                      </select>
                    </label>
                  </div>
                  <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <label class="attendance-check"><input v-model="row.is_school_day" type="checkbox" @change="autoSave" />学校日</label>
                    <select v-model="row.service_type" :aria-label="row.child_name + 'のサービス種別'" class="max-w-full rounded-md border-gray-300 text-sm" @change="autoSave">
                      <option value="">種別自動</option><option value="houday">放デイ</option><option value="jidou">児発</option>
                    </select>
                  </div>
                </div>
                <div class="attendance-field-group" role="group" :aria-label="row.child_name + 'の送迎・請求'">
                  <h4 class="attendance-field-title">送迎・請求</h4>
                  <div v-if="row.pickup_required" class="space-y-3">
                    <p v-if="row.yoyaku_pickup_time || row.yoyaku_dropoff_time" class="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">
                      <span v-if="row.yoyaku_pickup_time" class="mr-3">迎え {{ row.yoyaku_pickup_time }} {{ locationLabel(row.yoyaku_pickup_location) }}</span>
                      <span v-if="row.yoyaku_dropoff_time">送り {{ row.yoyaku_dropoff_time }} {{ locationLabel(row.yoyaku_dropoff_location) }}</span>
                    </p>
                    <div class="flex flex-wrap gap-3">
                      <label class="attendance-check"><input v-model="row.pickup_done" type="checkbox" @change="autoSave" />迎え完了</label>
                      <label class="attendance-check"><input v-model="row.dropoff_done" type="checkbox" @change="autoSave" />送り完了</label>
                    </div>
                  </div>
                  <p v-else class="text-sm text-gray-500">送迎対象外</p>
                  <label class="attendance-check mt-3"><input v-model="row.billing_target" type="checkbox" @change="autoSave" />請求対象</label>
                </div>
                <div class="attendance-field-group">
                  <h4 class="attendance-field-title">メモ</h4>
                  <input v-model="row.memo" type="text" :aria-label="row.child_name + 'のメモ'" placeholder="メモ" class="w-full rounded-md border-gray-300 px-3 py-2 text-sm" @change="autoSave" />
                </div>
              </div>
              <footer class="attendance-record-state">
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                  <span class="font-semibold">記録・連絡帳</span><ContactNoteStatus :state="row.contact_note_state" />
                  <span v-if="row.contact_note_home_entry">{{ row.contact_note_state?.home }}</span>
                  <span v-if="row.status === 'attended'">支援記録 {{ row.has_support_record ? '入力済み' : '未入力' }}</span>
                </div>
                <MenuDestination label="記録・連絡帳" :href="route('contact-notes.index', { date })" />
              </footer>
            </article>
          </div>

          <div class="p-4 border-t bg-gray-50">
            <p v-if="saveStatus === 'saving'" class="mb-2 text-xs text-gray-600">保存中...</p>
            <p v-else-if="saveStatus === 'saved'" class="mb-2 text-xs text-green-700">✓ 保存完了</p>
            <div v-else-if="saveStatus === 'error'" role="alert" class="mb-2 flex items-center gap-3 text-sm text-red-700">
              保存できませんでした。入力内容は画面に残っています。
              <button type="button" class="rounded-md border border-red-300 px-3 py-2 font-semibold" @click="autoSave">再保存</button>
            </div>
            <DisclosurePanel id="attendance-add-child" v-model="showAddPanel" title="児童を追加">
              <p v-if="!availableToAdd.length" class="text-sm text-gray-600">追加できる児童はいません。</p>
              <div v-else class="flex flex-wrap items-center gap-3">
                <select aria-label="追加する児童" v-model="selectedChildId" class="max-w-full rounded-md border-gray-300 text-sm">
                  <option value="">追加する児童を選択...</option>
                  <option v-for="c in availableToAdd" :key="c.id" :value="c.id">{{ c.name }}（{{ c.grade ?? '学年未登録' }}）</option>
                </select>
                <button type="button" @click="addChild" :disabled="!selectedChildId" class="rounded-md bg-primary-500 px-4 py-2 text-sm text-white disabled:opacity-40">追加</button>
              </div>
            </DisclosurePanel>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
