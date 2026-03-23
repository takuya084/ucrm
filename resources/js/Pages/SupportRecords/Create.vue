<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, computed, ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:          Object,
  date:           String,
  usageRecordId:  Number,
  programs:       Array,
  staffList:      Array,
  defaultStaffId: Number,
})

const form = reactive({
  child_id:                 props.child.id,
  usage_record_id:          props.usageRecordId,
  date:                     props.date,
  staff_id:                 props.defaultStaffId ?? null,
  condition:                'normal',
  behavior_note:            '',
  achievement_note:         '',
  challenge_note:           '',
  care_note:                props.child.care_note ?? '',
  next_action:              '',
  is_shared_with_guardian:  false,
  program_ids:              [],
  program_durations:        {},
  program_items:            {}, // { programId: [itemId, ...] }
})

// 項目展開状態
const expandedPrograms = ref(new Set())

const store = () => {
  Inertia.post(route('support-records.store'), form)
}

const CATEGORY_LABELS = {
  physical: '運動', cognitive: '認知・学習', social: '社会性・SST',
  life_skills: '生活スキル', other: 'その他',
}
const groupedPrograms = computed(() => {
  const order = ['physical', 'cognitive', 'social', 'life_skills', 'other']
  return order
    .map(cat => ({ cat, items: props.programs.filter(p => p.category === cat) }))
    .filter(g => g.items.length > 0)
})

const toggleProgram = (id, defaultDuration) => {
  const idx = form.program_ids.indexOf(id)
  if (idx >= 0) {
    form.program_ids.splice(idx, 1)
    delete form.program_durations[id]
    delete form.program_items[id]
    expandedPrograms.value.delete(id)
  } else {
    form.program_ids.push(id)
    form.program_durations[id] = defaultDuration
    form.program_items[id] = []
  }
}

const toggleExpand = (id) => {
  if (expandedPrograms.value.has(id)) {
    expandedPrograms.value.delete(id)
  } else {
    expandedPrograms.value.add(id)
  }
}

const toggleItem = (programId, itemId) => {
  if (!form.program_items[programId]) form.program_items[programId] = []
  const idx = form.program_items[programId].indexOf(itemId)
  if (idx >= 0) {
    form.program_items[programId].splice(idx, 1)
  } else {
    form.program_items[programId].push(itemId)
  }
}

const BEHAVIOR_PRESETS = [
  '落ち着いて過ごせた', '集中して取り組めた', '友達と仲良く関われた',
  '切り替えが難しかった', '気持ちの波があった', '表情が明るく元気だった',
]
const ACHIEVEMENT_PRESETS = [
  '最後まで取り組めた', '自分から挨拶できた', '指示をよく聞いて動けた',
  'ルールを守れた', '友達に優しく接した', '苦手なことに挑戦した',
]

const appendText = (field, text) => {
  form[field] = form[field] ? form[field] + '、' + text : text
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'

const CONDITION_OPTIONS = [
  { value: 'good',   label: '良好',  class: 'border-green-400  bg-green-50  text-green-700'  },
  { value: 'normal', label: '普通',  class: 'border-blue-400   bg-blue-50   text-blue-700'   },
  { value: 'poor',   label: '不調',  class: 'border-red-400    bg-red-50    text-red-700'    },
]
</script>

<template>
  <Head :title="child.name + ' - 支援記録入力'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('usage-records.index', { date })" class="text-gray-400 hover:text-gray-600 text-sm">
          ← 出席管理へ
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">
          支援記録 — {{ child.name }}（{{ date?.slice(0, 10) }}）
        </h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <div v-if="child.care_note" class="mb-5 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm">
            <span class="font-medium text-yellow-700">⚠ 配慮事項：</span>
            <span class="text-yellow-800">{{ child.care_note }}</span>
          </div>

          <form @submit.prevent="store" class="space-y-6">

            <!-- 様子 -->
            <section>
              <label :class="labelClass">今日の様子 <span class="text-red-500">*</span></label>
              <div class="flex gap-3 mt-1">
                <label
                  v-for="opt in CONDITION_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'flex-1 flex items-center justify-center py-3 border-2 rounded-lg cursor-pointer text-sm font-bold transition-all',
                    form.condition === opt.value ? opt.class : 'border-gray-200 text-gray-400 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.condition" :value="opt.value" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
            </section>

            <!-- 行動・様子 -->
            <section>
              <label :class="labelClass">行動・様子メモ</label>
              <div class="flex flex-wrap gap-1 mb-2">
                <button
                  v-for="p in BEHAVIOR_PRESETS" :key="p"
                  type="button"
                  @click="appendText('behavior_note', p)"
                  class="text-xs px-2 py-1 border border-gray-200 rounded bg-gray-50 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                >{{ p }}</button>
              </div>
              <textarea v-model="form.behavior_note" :class="inputClass" rows="3"
                placeholder="今日の行動・様子を記入してください" />
            </section>

            <!-- 成功体験 -->
            <section>
              <label :class="labelClass">成功体験・できたこと</label>
              <div class="flex flex-wrap gap-1 mb-2">
                <button
                  v-for="p in ACHIEVEMENT_PRESETS" :key="p"
                  type="button"
                  @click="appendText('achievement_note', p)"
                  class="text-xs px-2 py-1 border border-gray-200 rounded bg-green-50 hover:bg-green-100 text-green-700 transition-colors"
                >{{ p }}</button>
              </div>
              <textarea v-model="form.achievement_note" :class="inputClass" rows="2"
                placeholder="できたこと、成長が見られた場面など" />
            </section>

            <!-- 課題 -->
            <section>
              <label :class="labelClass">課題・気になること</label>
              <textarea v-model="form.challenge_note" :class="inputClass" rows="2"
                placeholder="気になった行動、次回検討したい支援など" />
            </section>

            <!-- 実施プログラム -->
            <section>
              <label :class="labelClass">実施したプログラム</label>
              <div v-for="group in groupedPrograms" :key="group.cat" class="mb-4">
                <div class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">
                  {{ CATEGORY_LABELS[group.cat] }}
                </div>
                <div class="space-y-2">
                  <div v-for="p in group.items" :key="p.id">
                    <!-- プログラム選択行 -->
                    <div
                      :class="[
                        'flex items-center gap-2 px-3 py-2 border rounded-lg text-sm transition-all',
                        form.program_ids.includes(p.id)
                          ? 'border-indigo-400 bg-indigo-50'
                          : 'border-gray-200 bg-white'
                      ]"
                    >
                      <!-- チェック部分（クリックでON/OFF） -->
                      <div
                        class="flex items-center gap-2 flex-1 cursor-pointer"
                        @click="toggleProgram(p.id, p.duration_minutes)"
                      >
                        <div :class="[
                          'w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0',
                          form.program_ids.includes(p.id) ? 'border-indigo-500 bg-indigo-500' : 'border-gray-300'
                        ]">
                          <svg v-if="form.program_ids.includes(p.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                        </div>
                        <span :class="form.program_ids.includes(p.id) ? 'text-indigo-700 font-medium' : 'text-gray-700'">
                          {{ p.name }}
                        </span>
                      </div>

                      <!-- 時間入力（選択時のみ） -->
                      <div v-if="form.program_ids.includes(p.id)" class="flex items-center gap-1" @click.stop>
                        <input
                          v-model="form.program_durations[p.id]"
                          type="number" min="5" max="180" step="5"
                          class="w-14 border border-indigo-300 rounded px-1 py-0.5 text-xs text-center"
                        />
                        <span class="text-xs text-indigo-500">分</span>
                      </div>

                      <!-- 詳細項目展開ボタン（項目がある場合のみ） -->
                      <button
                        v-if="form.program_ids.includes(p.id) && p.items?.length"
                        type="button"
                        @click.stop="toggleExpand(p.id)"
                        :class="[
                          'ml-1 px-2 py-1 text-xs rounded border transition-colors flex items-center gap-1',
                          expandedPrograms.has(p.id)
                            ? 'border-indigo-300 bg-indigo-100 text-indigo-600'
                            : 'border-gray-200 text-gray-500 hover:bg-gray-50'
                        ]"
                      >
                        <span>詳細</span>
                        <span v-if="(form.program_items[p.id]?.length ?? 0) > 0" class="bg-indigo-500 text-white rounded-full px-1">
                          {{ form.program_items[p.id].length }}
                        </span>
                        <svg :class="['w-3 h-3 transition-transform', expandedPrograms.has(p.id) ? 'rotate-180' : '']"
                          fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    </div>

                    <!-- 詳細項目アコーディオン -->
                    <div
                      v-if="form.program_ids.includes(p.id) && p.items?.length && expandedPrograms.has(p.id)"
                      class="ml-4 mt-1 p-3 bg-indigo-50 border border-indigo-200 rounded-lg"
                    >
                      <p class="text-xs text-indigo-500 mb-2">実施した内容を選択（難易度順）</p>
                      <div class="flex flex-wrap gap-2">
                        <label
                          v-for="item in p.items"
                          :key="item.id"
                          :class="[
                            'flex items-center gap-1.5 px-2.5 py-1.5 border rounded-full text-xs cursor-pointer transition-all',
                            (form.program_items[p.id] ?? []).includes(item.id)
                              ? 'border-indigo-500 bg-indigo-500 text-white'
                              : 'border-indigo-200 text-indigo-700 bg-white hover:bg-indigo-100'
                          ]"
                        >
                          <input
                            type="checkbox"
                            class="sr-only"
                            :checked="(form.program_items[p.id] ?? []).includes(item.id)"
                            @change="toggleItem(p.id, item.id)"
                          />
                          <span class="text-indigo-300 text-xs">{{ item.difficulty_order }}</span>
                          {{ item.name }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <p v-if="programs.length === 0" class="text-sm text-gray-400">
                プログラムが登録されていません
              </p>
            </section>

            <!-- 申し送り -->
            <section>
              <label :class="labelClass">次回への申し送り</label>
              <textarea v-model="form.next_action" :class="inputClass" rows="2"
                placeholder="次回支援で気をつけること、試したいことなど" />
            </section>

            <!-- 当日配慮メモ -->
            <section>
              <label :class="labelClass">
                本日の配慮メモ
                <span class="text-xs text-gray-400 ml-1">（今日特有の配慮があれば記入）</span>
              </label>
              <input v-model="form.care_note" type="text" :class="inputClass" />
            </section>

            <!-- 記録者 -->
            <section>
              <label :class="labelClass">記録者</label>
              <select v-model="form.staff_id" :class="inputClass">
                <option :value="null">― 選択してください ―</option>
                <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </section>

            <!-- 保護者共有 -->
            <section>
              <label class="flex items-center gap-3 cursor-pointer p-3 bg-blue-50 border border-blue-200 rounded">
                <input v-model="form.is_shared_with_guardian" type="checkbox" class="w-4 h-4" />
                <div>
                  <span class="text-sm font-medium text-blue-700">連絡帳として保護者に共有する</span>
                  <p class="text-xs text-blue-500 mt-0.5">チェックを入れると保護者共有フラグが立ちます</p>
                </div>
              </label>
            </section>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link
                :href="route('usage-records.index', { date })"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
              >キャンセル</Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                記録を保存
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
