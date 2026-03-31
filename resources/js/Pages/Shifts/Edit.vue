<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import ShiftCell from '@/Components/ShiftCell.vue'
import { reactive, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

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

const STATUS_LABELS = {
  draft:     { label: '下書き', class: 'bg-yellow-100 text-yellow-700' },
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

// 15分刻みの時刻選択肢を生成 (06:00〜22:00)
const timeOptions = []
for (let h = 6; h <= 22; h++) {
  for (let m = 0; m < 60; m += 15) {
    timeOptions.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'))
  }
}

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

  Inertia.post(route('shifts.bulk-save', props.shift.id), {
    entries:   flatEntries,
    day_notes: flatDayNotes,
  }, { preserveScroll: true })
}

const toggleStatus = () => {
  const newStatus = props.shift.status === 'draft' ? 'confirmed' : 'draft'
  if (newStatus === 'confirmed' && !confirm('このシフトを確定しますか？確定後はleaderロールでは編集できなくなります。')) return
  Inertia.patch(route('shifts.update-status', props.shift.id), { status: newStatus })
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
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <h2 class="font-semibold text-xl text-gray-800">{{ shift.year }}年{{ shift.month }}月 シフト表</h2>
          <span :class="['px-2 py-0.5 rounded text-xs font-medium', STATUS_LABELS[shift.status]?.class]">
            {{ STATUS_LABELS[shift.status]?.label }}
          </span>
        </div>
        <div class="flex gap-2 no-print">
          <button v-if="canEdit" @click="save"
            class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">
            保存
          </button>
          <button v-if="$page.props.auth.staff_role === 'admin'"
            @click="toggleStatus"
            class="px-4 py-2 text-sm border rounded hover:bg-gray-50">
            {{ shift.status === 'draft' ? '確定する' : '下書きに戻す' }}
          </button>
          <button @click="printShift"
            class="px-4 py-2 text-sm border rounded hover:bg-gray-50">
            印刷
          </button>
        </div>
      </div>
    </template>

    <div class="py-4">
      <div class="mx-auto sm:px-4 lg:px-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
          <FlashMessage />

          <!-- 配置基準ルール -->
          <details class="mb-3 text-sm border rounded">
            <summary class="px-3 py-2 cursor-pointer text-gray-600 hover:bg-gray-50 select-none">
              配置基準チェックのルール
            </summary>
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
          </details>

          <div class="overflow-x-auto">
            <table class="shift-table w-full text-xs border-collapse">
              <thead>
                <tr class="bg-gray-50">
                  <th class="border px-1 py-1.5 text-center w-10 sticky left-0 bg-gray-50 z-10">日</th>
                  <th class="border px-1 py-1.5 text-center w-8">曜</th>
                  <th class="border px-1 py-1.5 text-center min-w-[110px]">備考</th>
                  <th v-for="s in staffMembers" :key="s.id"
                    class="border px-1 py-1.5 text-center min-w-[90px]">
                    <div class="whitespace-nowrap">{{ s.name }}</div>
                    <div v-if="staffTags[s.id]?.length" class="flex flex-wrap justify-center gap-0.5 mt-0.5">
                      <span v-for="(tag, ti) in staffTags[s.id]" :key="ti"
                        :class="[tag.colorClass, 'px-1 py-0 rounded text-[9px] leading-tight font-normal']">
                        {{ tag.name }}
                      </span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in dates" :key="d.date"
                  :class="DOW_COLORS[d.day_of_week] || ''">
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
                      class="w-full text-xs px-1 py-0.5"
                    />
                    <div v-if="dailyCheck[d.date] && !dailyCheck[d.date].noChildren"
                      class="mt-0.5 text-[11px] leading-tight whitespace-nowrap"
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
                  <td v-for="s in staffMembers" :key="s.id" class="border px-1 py-1">
                    <ShiftCell
                      :modelValue="grid[d.date][s.id]"
                      @update:modelValue="grid[d.date][s.id] = $event"
                      :disabled="!canEdit"
                      :labels="labels"
                      :timeOptions="timeOptions"
                    />
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

@media print {
  @page {
    size: A4 landscape;
    margin: 10mm;
  }
  nav, .no-print, header button {
    display: none !important;
  }
  .shift-table {
    font-size: 7pt;
  }
  .shift-table td,
  .shift-table th {
    padding: 1px 2px;
    border: 0.5pt solid #333;
  }
  .shift-table select,
  .shift-table input {
    border: none;
    background: transparent;
    font-size: 7pt;
    padding: 0;
    -webkit-appearance: none;
    appearance: none;
  }
  .shift-table {
    width: 100%;
    table-layout: fixed;
  }
  .bg-white.shadow-sm {
    box-shadow: none;
    padding: 0;
  }
}
</style>
