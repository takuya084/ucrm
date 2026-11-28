<script setup>
import {shiftTimes} from '@/lib/shiftClipboard.mjs'
import {useShiftClipboard} from '@/lib/useShiftClipboard.mjs'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import DisclosurePanel from '@/Components/DisclosurePanel.vue'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import ShiftCell from '@/Components/ShiftCell.vue'
import { reactive, computed } from 'vue'

const props = defineProps({
  shift:                Object,   // { id, year, month, status }
  staffMembers:         Array,    // [{ id, name, role }]
  dates:                Array,    // [{ date, day, day_of_week, day_label }]
  entries:              Array,    // [{ staff_id, date, start_time, work_type, note }]
  dayNotes:             Object,   // { 'YYYY-MM-DD': 'note' }
  labels:               Array,    // [{ name, is_off }]
  canEdit:              Boolean,
  childCounts:          Object,   // { 'YYYY-MM-DD': number }
  staffQualifications:  Object,   // { staffId: ['hoikushi', ...] }
  qualificationTypes:   Object,   // { code: { name, color } }
  capacity:             Number,
})

const page = usePage()
const billingQuery = computed(() => new URLSearchParams(page.url.split('?')[1]))
const billingPeriod = computed(() => {
  const id = billingQuery.value.get('billing_period')
  return /^[1-9]\d*$/.test(id ?? '') ? id : null
})
const billingDate = computed(() => billingQuery.value.get('billing_date'))

const STATUS_LABELS = {
  draft:     { label: '下書き', class: 'bg-amber-100 text-amber-700' },
  confirmed: { label: '確定',   class: 'bg-green-100 text-green-700' },
}

// エントリをグリッド構造に変換: { 'YYYY-MM-DD': { staffId: { start_time, work_type, note } } }
const grid = reactive({})
props.dates.forEach(d => {
  grid[d.date] = {}
  props.staffMembers.forEach(s => {
    grid[d.date][s.id] = { start_time: null, work_type: '', note: null }
  })
})
props.entries.forEach(e => {
  if (grid[e.date] && grid[e.date][e.staff_id]) {
    grid[e.date][e.staff_id] = {
      start_time: e.start_time,
      work_type:  e.work_type,
      note:       e.note,
    }
  }
})

const notes = reactive({ ...props.dayNotes })

const timeOptions = shiftTimes()
const clipboard = useShiftClipboard({
  rows: () => props.dates.length, cols: () => props.staffMembers.length * 2,
  read: (row, col) => grid[props.dates[row].date][props.staffMembers[Math.floor(col / 2)].id][col % 2 ? 'start_time' : 'work_type'],
  write: (row, col, value) => { grid[props.dates[row].date][props.staffMembers[Math.floor(col / 2)].id][col % 2 ? 'start_time' : 'work_type'] = value },
  labels: () => props.labels, times: timeOptions, canEdit: () => props.canEdit,
})

const DOW_COLORS = {
  sat: 'bg-blue-50',
  sun: 'bg-red-50',
}

const save = () => {
  const flatEntries = []
  for (const [date, staffMap] of Object.entries(grid)) {
    for (const [staffId, data] of Object.entries(staffMap)) {
      flatEntries.push({
        staff_id:   Number(staffId),
        date,
        start_time: data.start_time || null,
        work_type:  data.work_type,
        note:       data.note || null,
      })
    }
  }
  const flatDayNotes = Object.entries(notes).map(([date, note]) => ({ date, note: note || null }))

  router.post(route('shifts.bulk-save', props.shift.id), {
    entries:   flatEntries,
    day_notes: flatDayNotes,
  }, { preserveScroll: true })
}

const toggleStatus = () => {
  const newStatus = props.shift.status === 'draft' ? 'confirmed' : 'draft'
  if (newStatus === 'confirmed' && !confirm('このシフトを確定しますか？確定後は児発管が編集できなくなります。')) return
  router.patch(route('shifts.update-status', props.shift.id), { status: newStatus })
}

const printShift = () => window.print()

// ── 人員配置チェック ──

const offLabels = new Set(props.labels.filter(l => l.is_off).map(l => l.name))

const ROLE_TAGS = {
  admin:  { name: '管理者', color: 'red' },
  leader: { name: '児発管', color: 'teal' },
}

const TAG_COLORS = {
  blue:   'bg-blue-100 text-blue-700',
  green:  'bg-green-100 text-green-700',
  purple: 'bg-purple-100 text-purple-700',
  orange: 'bg-orange-100 text-orange-700',
  gray:   'bg-gray-100 text-gray-600',
  red:    'bg-red-100 text-red-700',
  teal:   'bg-teal-100 text-teal-700',
}

const staffTags = computed(() => {
  const tags = {}
  props.staffMembers.forEach(s => {
    const t = []
    if (ROLE_TAGS[s.role]) {
      t.push({ name: ROLE_TAGS[s.role].name, colorClass: TAG_COLORS[ROLE_TAGS[s.role].color] })
    }
    const quals = props.staffQualifications?.[s.id] || []
    quals.forEach(q => {
      const info = props.qualificationTypes?.[q]
      if (info) {
        t.push({ name: info.name, colorClass: TAG_COLORS[info.color] || TAG_COLORS.gray })
      }
    })
    tags[s.id] = t
  })
  return tags
})

const requiredStaff = (childCount) => {
  if (childCount <= 0) return 0
  if (childCount <= 10) return 2
  return 2 + Math.ceil((childCount - 10) / 5)
}

const dailyCheck = computed(() => {
  const result = {}
  props.dates.forEach(d => {
    const childCount = props.childCounts?.[d.date] ?? 0
    const needed = requiredStaff(childCount)

    // 出勤スタッフ
    const working = []
    props.staffMembers.forEach(s => {
      const cell = grid[d.date]?.[s.id]
      if (cell && cell.work_type && !offLabels.has(cell.work_type)) {
        working.push(s)
      }
    })
    const assigned = working.length

    // 有資格者数（保育士 or 児童指導員）
    const qualified = working.filter(s => {
      const quals = props.staffQualifications?.[s.id] || []
      return quals.includes('hoikushi') || quals.includes('jidou_shidouin')
    }).length

    const neededQualified = Math.ceil(needed / 2)

    // 児発管出勤チェック
    const leaderPresent = working.some(s => s.role === 'leader')

    // ── 加算チェック ──
    // 児童指導員等加配加算: 基準人数を超えて有資格者（保育士/児童指導員）を1名以上配置
    const extraQualified = qualified - neededQualified
    const hasKahaiKasan = extraQualified >= 1

    // 専門的支援加算: PT/OT/ST/心理士が1名以上出勤
    const senmonPresent = working.some(s => {
      const quals = props.staffQualifications?.[s.id] || []
      return quals.includes('pt') || quals.includes('ot') || quals.includes('st') || quals.includes('shinrishi')
    })

    // 強度行動障害児支援加算: 強度行動障害研修修了者が1名以上出勤
    const kyoudoPresent = working.some(s => {
      const quals = props.staffQualifications?.[s.id] || []
      return quals.includes('kyoudo')
    })

    // 警告理由
    const warnings = []
    if (childCount > 0) {
      if (assigned < needed) warnings.push(`職員${needed - assigned}名不足`)
      if (qualified < neededQualified) warnings.push('有資格者不足')
      if (!leaderPresent) warnings.push('児発管不在')
    }

    // 加算情報
    const kasans = []
    if (childCount > 0) {
      if (hasKahaiKasan) kasans.push('加配')
      if (senmonPresent)  kasans.push('専門的支援')
      if (kyoudoPresent)  kasans.push('強度行動障害')
    }

    result[d.date] = {
      childCount,
      needed,
      assigned,
      qualified,
      leaderPresent,
      ok: warnings.length === 0 && childCount > 0,
      warnings,
      kasans,
      noChildren: childCount === 0,
    }
  })
  return result
})
</script>

<template>
  <Head :title="`${shift.year}年${shift.month}月 シフト表`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <h2 class="font-semibold text-xl text-gray-800">{{ shift.year }}年{{ shift.month }}月 シフト表</h2>
          <span :class="['px-2 py-0.5 rounded-md text-xs font-medium', STATUS_LABELS[shift.status]?.class]">
            {{ STATUS_LABELS[shift.status]?.label }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 no-print">
          <Link v-if="billingPeriod" :href="route('billing.show', { billingPeriod, check: 1 })"
            class="px-3 py-2 text-sm font-medium text-primary-700 underline">請求チェックに戻る</Link>
          <button v-if="canEdit" @click="save"
            class="px-4 py-2 text-sm bg-primary-500 text-white rounded-md hover:bg-primary-600">
            保存
          </button>
          <button v-if="$page.props.auth.staff_role === 'admin'"
            @click="toggleStatus"
            class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
            {{ shift.status === 'draft' ? '確定する' : '下書きに戻す' }}
          </button>
          <button @click="printShift"
            class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
            印刷
          </button>
        </div>
      </div>
    </template>

    <div class="shift-print-page py-4">
      <div class="shift-print-frame mx-auto sm:px-4 lg:px-6">
        <div class="shift-print-card bg-white border border-gray-200 sm:rounded-lg p-4">
          <div class="shift-print-controls">
          <FlashMessage />
          <ValidationErrors class="mb-3" />
          <DisclosurePanel id="shift-copy-help" title="コピー・貼り付けの使い方" class="mb-3">
            <p class="p-3 text-sm">勤務区分や時刻を選び、⌘／Ctrl＋C・Vでコピー・貼り付けできます。Shift＋クリック、またはShift＋矢印キーで範囲を選べます。Excelは職員ごとに「勤務区分・開始時刻」の2列で貼り付けてください。未登録の区分・選択肢にない時刻・はみ出す範囲は、全体を変更せず拒否します。</p>
          </DisclosurePanel>
          <p role="status" aria-live="polite" class="text-sm text-gray-600 mb-2">{{ clipboard.status.value }}</p>

          <!-- 配置基準ルール -->
          <DisclosurePanel id="staffing-rules" title="配置基準チェックのルール" class="mb-3">
            <div class="px-4 py-3 bg-gray-50 border-t text-gray-700 space-y-2">
              <div class="flex items-start gap-2">
                <span class="text-green-600 font-bold mt-0.5">✓</span>
                <span>すべての基準を満たしている場合は<span class="text-green-600 font-medium">緑色</span>で表示されます。</span>
              </div>
              <div class="flex items-start gap-2">
                <span class="text-red-600 font-bold mt-0.5">⚠</span>
                <span>基準を満たしていない場合は<span class="text-red-600 font-medium">赤色</span>で警告が表示されます。</span>
              </div>
              <table class="mt-2 text-xs w-full max-w-lg">
                <tbody>
                  <tr class="border-b">
                    <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">必要職員数</td>
                    <td class="py-1.5">児童10名以下 → 2名、以降児童5名増ごとに+1名</td>
                  </tr>
                  <tr class="border-b">
                    <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">有資格者</td>
                    <td class="py-1.5">必要職員数の半数以上が保育士または児童指導員であること</td>
                  </tr>
                  <tr>
                    <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">児発管</td>
                    <td class="py-1.5">児童発達支援管理責任者が出勤していること</td>
                  </tr>
                </tbody>
              </table>

              <div class="mt-3 pt-3 border-t">
                <div class="font-medium text-gray-600 mb-1">加算チェック（<span class="text-blue-600">青色</span>で表示）</div>
                <table class="text-xs w-full max-w-lg">
                  <tbody>
                    <tr class="border-b">
                      <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">加配</td>
                      <td class="py-1.5">基準の有資格者数を超えて保育士・児童指導員を1名以上多く配置</td>
                    </tr>
                    <tr class="border-b">
                      <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">専門的支援</td>
                      <td class="py-1.5">PT・OT・ST・心理士のいずれかが1名以上出勤</td>
                    </tr>
                    <tr>
                      <td class="py-1.5 pr-3 font-medium text-gray-600 whitespace-nowrap align-top">強度行動障害</td>
                      <td class="py-1.5">強度行動障害支援者養成研修の修了者が1名以上出勤</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p class="text-[11px] text-gray-400 mt-2">※ 備考欄の下に「利用児童数 / 必要職員数 / 配置職員数」が日ごとに表示されます。利用児童がいない日は非表示です。</p>
            </div>
          </DisclosurePanel>

          </div>

          <div class="shift-table-scroll overflow-x-auto">
            <table class="shift-table w-full text-xs border-collapse" aria-label="月間シフト入力表"
              @focusin="clipboard.focus" @pointerdown="clipboard.pointer" @copy="clipboard.copy" @paste="clipboard.paste" @keydown="clipboard.keydown">
              <colgroup>
                <col class="shift-day-column" />
                <col class="shift-weekday-column" />
                <col class="shift-notes-column" />
                <col v-for="s in staffMembers" :key="s.id" />
              </colgroup>
              <thead>
                <tr class="bg-gray-50">
                  <th class="border px-1 py-1.5 text-center w-10 sticky left-0 bg-gray-50 z-10">日</th>
                  <th class="border px-1 py-1.5 text-center w-8">曜</th>
                  <th class="border px-1 py-1.5 text-center min-w-[110px]">備考</th>
                  <th v-for="s in staffMembers" :key="s.id"
                    class="border px-1 py-1.5 text-center min-w-[90px]">
                    <div class="shift-staff-name">{{ s.name }}</div>
                    <div v-if="staffTags[s.id]?.length" class="flex flex-wrap justify-center gap-0.5 mt-0.5">
                      <span v-for="(tag, ti) in staffTags[s.id]" :key="ti"
                        :class="[tag.colorClass, 'px-1 py-0 rounded-md text-[9px] leading-tight font-normal']">
                        {{ tag.name }}
                      </span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(d, rowIndex) in dates" :key="d.date"
                  :class="d.date === billingDate ? 'bg-primary-100' : DOW_COLORS[d.day_of_week] || ''">
                  <td class="border px-1 py-1 text-center font-medium sticky left-0 z-10"
                    :class="[DOW_COLORS[d.day_of_week] || 'bg-white']">
                    {{ d.day }}
                  </td>
                  <td class="border px-1 py-1 text-center"
                    :class="{
                      'text-blue-500': d.day_of_week === 'sat',
                      'text-red-500': d.day_of_week === 'sun',
                    }">
                    {{ d.day_label }}
                  </td>
                  <td class="border px-1 py-1">
                    <input
                      v-model="notes[d.date]"
                      type="text"
                      :disabled="!canEdit"
                      placeholder=""
                      class="shift-screen-only w-full text-xs px-1 py-0.5"
                    />
                    <div class="shift-print-value shift-print-notes" aria-hidden="true">{{ notes[d.date] || '\u00a0' }}</div>
                    <div v-if="dailyCheck[d.date] && !dailyCheck[d.date].noChildren"
                      class="shift-staffing-summary mt-0.5 text-[11px] leading-tight whitespace-nowrap"
                      :class="dailyCheck[d.date].ok ? 'text-green-600' : 'text-red-600 font-medium'">
                      <span>{{ dailyCheck[d.date].childCount }}名</span>
                      <span class="mx-0.5">/</span>
                      <span>必要{{ dailyCheck[d.date].needed }}</span>
                      <span class="mx-0.5">/</span>
                      <span>配置{{ dailyCheck[d.date].assigned }}</span>
                      <span class="ml-0.5">{{ dailyCheck[d.date].ok ? '✓' : '⚠' }}</span>
                      <div v-if="dailyCheck[d.date].warnings.length" class="text-red-500">
                        {{ dailyCheck[d.date].warnings.join(', ') }}
                      </div>
                      <div v-if="dailyCheck[d.date].kasans.length" class="text-blue-600">
                        {{ dailyCheck[d.date].kasans.join(', ') }}
                      </div>
                    </div>
                  </td>
                  <td v-for="(s, staffIndex) in staffMembers" :key="s.id" class="border px-1 py-1">
                    <ShiftCell class="shift-screen-only"
                      :modelValue="grid[d.date][s.id]"
                      @update:modelValue="grid[d.date][s.id] = $event"
                      :disabled="!canEdit"
                      :labels="labels"
                      :timeOptions="timeOptions"
                      :rowIndex="rowIndex" :colOffset="staffIndex * 2" :selected="clipboard.selected"
                      :contextLabel="`${d.day}日 ${s.name}`"
                    />
                    <div class="shift-print-value" aria-hidden="true">
                      <div>{{ grid[d.date][s.id].work_type || '―' }}</div>
                      <div v-if="!offLabels.has(grid[d.date][s.id].work_type)">{{ grid[d.date][s.id].start_time?.slice(0, 5) || '--:--' }}</div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<style>
/* テーブル罫線を濃く */
.shift-table th,
.shift-table td {
  border-color: #9ca3af; /* gray-400 */
}

/* セル内のフォーム要素は枠線なし */
.shift-table select,
.shift-table input {
  border: none;
  outline: none;
  background: transparent;
}
.shift-table select:focus,
.shift-table input:focus {
  box-shadow: none;
  ring: none;
  --tw-ring-shadow: none;
}

/* Values are ordinary text on paper, so names, notes and shift labels can wrap. */
.shift-staff-name {
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: normal;
  line-height: 1.25;
}
.shift-print-value { display: none; }

@media print {
  @page { size: A4 landscape; margin: 10mm; }

  body:has(.shift-print-page) .workspace-sidebar,
  body:has(.shift-print-page) .workspace-accountbar,
  body:has(.shift-print-page) #workspace-menu,
  body:has(.shift-print-page) .workspace-shell > a,
  body:has(.shift-print-page) .no-print,
  body:has(.shift-print-page) #main-content > [role="alert"],
  .shift-print-page .shift-print-controls,
  .shift-print-page .shift-screen-only {
    display: none !important;
  }
  body:has(.shift-print-page) .workspace-shell,
  body:has(.shift-print-page) .workspace-shell > div,
  .shift-print-page,
  .shift-print-page .shift-print-frame,
  .shift-print-page .shift-print-card {
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    max-width: none !important;
    background: white !important;
    box-shadow: none !important;
  }
  body:has(.shift-print-page) .workspace-page-header {
    position: static !important;
    padding: 0 0 3mm !important;
    border: 0;
  }
  .shift-print-page .shift-table-scroll { overflow: visible !important; }
  .shift-print-page .shift-table {
    width: 100%;
    table-layout: fixed;
    font-size: 7pt;
    line-height: 1.15;
  }
  .shift-print-page .shift-day-column { width: 7mm; }
  .shift-print-page .shift-weekday-column { width: 6mm; }
  .shift-print-page .shift-notes-column { width: 40mm; }
  .shift-print-page .shift-table thead { display: table-header-group; }
  .shift-print-page .shift-table tr { break-inside: avoid; page-break-inside: avoid; }
  .shift-print-page .shift-table th,
  .shift-print-page .shift-table td {
    min-width: 0 !important;
    width: auto;
    padding: 0.35mm 0.4mm;
    border: 0.5pt solid #333;
    position: static !important;
    overflow-wrap: anywhere;
  }
  .shift-print-page .shift-table th { vertical-align: top; }
  .shift-print-page .shift-print-value { display: block; }
  .shift-print-page .shift-print-notes { white-space: pre-wrap; }
  .shift-print-page .shift-staffing-summary {
    white-space: normal !important;
    overflow-wrap: anywhere;
    font-size: 6pt;
    line-height: 1.15;
  }
}
</style>
