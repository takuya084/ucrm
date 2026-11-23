<script setup>
import {shiftTimes} from '@/lib/shiftClipboard.mjs'
import {useShiftClipboard} from '@/lib/useShiftClipboard.mjs'
import DisclosurePanel from '@/Components/DisclosurePanel.vue'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  staff:     Object,   // { id, name }
  patterns:  Array,    // [{ day_of_week, start_time, work_type }] x 7
  dayLabels: Object,   // { mon: '月', ... }
  labels:    Array,    // [{ name, is_off }]
})

const form = reactive({
  patterns: props.patterns.map(p => ({ ...p })),
})

const isOff = (type) => {
  if (!type) return false
  const label = props.labels.find(l => l.name === type)
  return label ? label.is_off : false
}

const timeOptions = shiftTimes()
const clipboard = useShiftClipboard({
  rows: () => form.patterns.length, cols: () => 2,
  read: (row, col) => form.patterns[row][col ? 'start_time' : 'work_type'],
  write: (row, col, value) => { form.patterns[row][col ? 'start_time' : 'work_type'] = value },
  labels: () => props.labels, times: timeOptions, canEdit: () => true,
})

const save = () => {
  router.patch(route('staff.work-patterns.update', props.staff.id), form)
}
</script>

<template>
  <Head :title="`勤務パターン設定 - ${staff.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">勤務パターン設定 — {{ staff.name }}</h2>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <FlashMessage />
          <BreezeValidationErrors class="mb-4" />
          <DisclosurePanel id="pattern-copy-help" title="コピー・貼り付けの使い方" class="mb-3">
            <p class="p-3 text-sm">⌘／Ctrl＋C・Vでコピー・貼り付けできます。Shift＋クリック、またはShift＋矢印キーで範囲選択。Excelは「勤務区分・開始時刻」の2列、月〜日の7行で貼り付けてください。不正な値を含む範囲は全体を変更せず拒否します。</p>
          </DisclosurePanel>
          <p role="status" aria-live="polite" class="text-sm text-gray-600 mb-2">{{ clipboard.status.value }}</p>

          <form @submit.prevent="save" class="space-y-2">
            <div class="workspace-table-scroll overflow-x-auto" tabindex="0" role="region" aria-label="横スクロールできる一覧"><table class="w-full text-sm" aria-label="曜日別勤務パターン入力表"
              @focusin="clipboard.focus" @pointerdown="clipboard.pointer" @copy="clipboard.copy" @paste="clipboard.paste" @keydown="clipboard.keydown">
              <thead>
                <tr class="border-b text-left text-gray-500">
                  <th class="py-2 px-2 w-12">曜日</th>
                  <th class="py-2 px-2">勤務区分</th>
                  <th class="py-2 px-2 w-32">開始時刻</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(p, i) in form.patterns" :key="p.day_of_week"
                  class="border-b"
                  :class="{
                    'bg-blue-50': p.day_of_week === 'sat',
                    'bg-red-50': p.day_of_week === 'sun',
                  }">
                  <td class="py-2 px-2 font-medium"
                    :class="{
                      'text-blue-500': p.day_of_week === 'sat',
                      'text-red-500': p.day_of_week === 'sun',
                    }">
                    {{ dayLabels[p.day_of_week] }}
                  </td>
                  <td class="py-2 px-2">
                    <select :value="p.work_type" @change="p.work_type = $event.target.value; isOff(p.work_type) && (p.start_time = null)"
                      data-shift-cell :data-clip-row="i" :data-clip-col="0"
                      :aria-label="`${dayLabels[p.day_of_week]}曜日 勤務区分`"
                      :class="{'shift-clipboard-selected': clipboard.selected(i, 0)}"
                      class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                      <option value="">―</option>
                      <option v-for="l in labels" :key="l.name" :value="l.name">{{ l.name }}</option>
                    </select>
                  </td>
                  <td class="py-2 px-2">
                    <select
                      data-shift-cell :data-clip-row="i" :data-clip-col="1"
                      :aria-label="`${dayLabels[p.day_of_week]}曜日 開始時刻`"
                      :class="{'shift-clipboard-selected': clipboard.selected(i, 1)}"
                      v-if="!isOff(p.work_type)"
                      v-model="p.start_time"
                      class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm"
                    >
                      <option :value="null">--:--</option>
                      <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <span v-else class="text-gray-400 text-sm">—</span>
                  </td>
                </tr>
              </tbody>
            </table></div>

            <div class="flex justify-between items-center pt-4 border-t">
              <Link :href="route('staff.index')" class="text-sm text-gray-500 hover:underline">
                ← 職員一覧に戻る
              </Link>
              <button type="submit"
                class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600">
                保存する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
