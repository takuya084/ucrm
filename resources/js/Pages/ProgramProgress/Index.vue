<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import { ref, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  programs:            Array,
  selectedProgram:     Object,
  children:            Array,
  progress:            Object, // { child_id: { item_id: 'practicing'|'mastered'|null } }
  categoryFilter:      String,
  availableCategories: Array,
  todayChildIds:       Array,
})

const CATEGORY_LABELS = {
  physical:    '運動',
  cognitive:   '認知・学習',
  social:      '社会性・SST',
  life_skills: '生活スキル',
  other:       'その他',
}

// 今日のみ表示トグル
const todayOnly = ref(false)
const todaySet  = new Set(props.todayChildIds ?? [])
const displayChildren = computed(() =>
  todayOnly.value ? props.children.filter(c => todaySet.has(c.id)) : props.children
)

// ローカルで進度状態を管理（UIの即時反映用）
const localProgress = ref(JSON.parse(JSON.stringify(props.progress ?? {})))

const STATUS_CYCLE = [null, 'practicing', 'mastered']
const STATUS_CONFIG = {
  null:       { label: '―',    bg: 'bg-gray-100',   text: 'text-gray-300', border: 'border-gray-200',  icon: '' },
  practicing: { label: '練習中', bg: 'bg-yellow-100', text: 'text-yellow-700', border: 'border-yellow-300', icon: '▶' },
  mastered:   { label: '達成',  bg: 'bg-green-100',  text: 'text-green-700',  border: 'border-green-300',  icon: '✓' },
}

const cycleStatus = (childId, itemId) => {
  const current = localProgress.value[childId]?.[itemId] ?? null
  const nextIdx  = (STATUS_CYCLE.indexOf(current) + 1) % STATUS_CYCLE.length
  const next     = STATUS_CYCLE[nextIdx]

  if (!localProgress.value[childId]) localProgress.value[childId] = {}
  localProgress.value[childId][itemId] = next

  Inertia.post(route('program-progress.update'), {
    child_id:        childId,
    program_item_id: itemId,
    status:          next ?? 'none',
  }, { preserveScroll: true, preserveState: true })
}

const goToCategory = (cat) => {
  Inertia.get(route('program-progress.index'), { category: cat }, { preserveState: false })
}
const goToProgram = (programId) => {
  Inertia.get(route('program-progress.index'), {
    category:   props.categoryFilter,
    program_id: programId,
  }, { preserveState: false })
}

// 児童ごとの現在レベル（達成済み最高難易度+1）
const childCurrentLevel = computed(() => {
  if (!props.selectedProgram) return {}
  const items = props.selectedProgram.items
  const result = {}
  for (const child of displayChildren.value) {
    const cp = localProgress.value[child.id] ?? {}
    let masteredCount = 0
    for (const item of items) {
      if (cp[item.id] === 'mastered') masteredCount++
    }
    result[child.id] = masteredCount // 達成済み数
  }
  return result
})

// 全体の達成率
const overallStats = computed(() => {
  if (!props.selectedProgram || !displayChildren.value.length) return null
  const items = props.selectedProgram.items
  const total = displayChildren.value.length * items.length
  if (total === 0) return null
  let mastered = 0, practicing = 0
  for (const child of displayChildren.value) {
    const cp = localProgress.value[child.id] ?? {}
    for (const item of items) {
      if (cp[item.id] === 'mastered') mastered++
      else if (cp[item.id] === 'practicing') practicing++
    }
  }
  return { total, mastered, practicing, rate: Math.round(mastered / total * 100) }
})
</script>

<template>
  <Head title="療育進度管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4 flex-wrap">
        <h2 class="font-semibold text-xl text-gray-800">療育進度管理</h2>
        <button
          type="button"
          @click="todayOnly = !todayOnly"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-full border font-medium transition-colors',
            todayOnly
              ? 'bg-indigo-500 text-white border-indigo-500'
              : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
          ]"
        >
          <span>{{ todayOnly ? '今日の児童のみ' : '全児童' }}</span>
          <span v-if="todayOnly" class="bg-white text-indigo-600 rounded-full text-xs px-1.5 font-bold">
            {{ todaySet.size }}
          </span>
        </button>
        <span v-if="todayOnly && todaySet.size === 0" class="text-xs text-yellow-600">
          本日の出席記録がありません
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

        <!-- カテゴリタブ -->
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="cat in availableCategories"
            :key="cat"
            @click="goToCategory(cat)"
            :class="[
              'px-4 py-2 rounded-full text-sm font-medium border transition-colors',
              categoryFilter === cat
                ? 'bg-indigo-500 text-white border-indigo-500'
                : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
            ]"
          >{{ CATEGORY_LABELS[cat] ?? cat }}</button>
        </div>

        <!-- プログラムが見つからない場合 -->
        <div v-if="programs.length === 0" class="bg-white rounded-lg shadow-sm p-10 text-center text-gray-400">
          <p>このカテゴリには詳細項目が登録されたプログラムがありません。</p>
          <Link :href="route('programs.index')" class="mt-3 inline-block text-sm text-indigo-500 hover:underline">
            プログラム管理で項目を追加 →
          </Link>
        </div>

        <template v-else>
          <!-- プログラム選択 -->
          <div class="flex gap-2 flex-wrap items-center">
            <span class="text-xs text-gray-500">プログラム：</span>
            <button
              v-for="p in programs"
              :key="p.id"
              @click="goToProgram(p.id)"
              :class="[
                'px-3 py-1.5 rounded border text-sm transition-colors',
                selectedProgram?.id === p.id
                  ? 'bg-indigo-50 border-indigo-400 text-indigo-700 font-medium'
                  : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
              ]"
            >{{ p.name }}</button>
          </div>

          <!-- 選択プログラムの統計 -->
          <div v-if="selectedProgram && overallStats" class="flex gap-4 items-center bg-white rounded-lg shadow-sm px-5 py-3">
            <div class="text-sm font-semibold text-gray-700">{{ selectedProgram.name }}</div>
            <div class="flex gap-3 ml-4 text-sm">
              <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-green-400 inline-block"></span>
                達成 {{ overallStats.mastered }}
              </span>
              <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-yellow-400 inline-block"></span>
                練習中 {{ overallStats.practicing }}
              </span>
              <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-gray-200 inline-block"></span>
                未着手 {{ overallStats.total - overallStats.mastered - overallStats.practicing }}
              </span>
            </div>
            <div class="ml-auto text-xs text-gray-400">
              クリックで 未着手 → 練習中 → 達成 と切り替わります
            </div>
          </div>

          <!-- 進度グリッド -->
          <div v-if="selectedProgram" class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm border-collapse">
                <thead>
                  <tr class="bg-gray-50 border-b">
                    <!-- 固定列：児童名 -->
                    <th class="sticky left-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs text-gray-500 font-semibold w-32 border-r">
                      児童名
                    </th>
                    <th class="px-2 py-3 text-center text-xs text-gray-500 font-medium w-16 border-r">
                      達成数
                    </th>
                    <!-- 項目列 -->
                    <th
                      v-for="item in selectedProgram.items"
                      :key="item.id"
                      class="px-2 py-2 text-center min-w-[80px] border-r last:border-r-0"
                    >
                      <div class="text-xs font-medium text-gray-700">{{ item.name }}</div>
                      <div class="text-xs text-indigo-400 mt-0.5">難易度 {{ item.difficulty_order }}</div>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr
                    v-for="child in displayChildren"
                    :key="child.id"
                    class="hover:bg-gray-50 transition-colors"
                  >
                    <!-- 児童名（固定） -->
                    <td class="sticky left-0 z-10 bg-white px-4 py-2.5 border-r">
                      <Link
                        :href="route('children.show', child.id)"
                        class="font-medium text-gray-900 hover:text-indigo-600 text-sm block leading-tight"
                      >{{ child.name }}</Link>
                      <span class="text-xs text-gray-400">{{ child.grade }}</span>
                    </td>

                    <!-- 達成数バッジ -->
                    <td class="px-2 py-2.5 text-center border-r">
                      <span :class="[
                        'text-sm font-bold',
                        childCurrentLevel[child.id] === selectedProgram.items.length
                          ? 'text-green-600' : 'text-gray-700'
                      ]">
                        {{ childCurrentLevel[child.id] }}
                      </span>
                      <span class="text-xs text-gray-300"> /{{ selectedProgram.items.length }}</span>
                    </td>

                    <!-- 項目セル -->
                    <td
                      v-for="item in selectedProgram.items"
                      :key="item.id"
                      class="px-1.5 py-2.5 text-center border-r last:border-r-0"
                    >
                      <button
                        type="button"
                        @click="cycleStatus(child.id, item.id)"
                        :class="[
                          'w-full min-w-[68px] px-1 py-1.5 rounded border text-xs font-medium transition-all hover:opacity-80 active:scale-95',
                          STATUS_CONFIG[localProgress[child.id]?.[item.id] ?? null].bg,
                          STATUS_CONFIG[localProgress[child.id]?.[item.id] ?? null].text,
                          STATUS_CONFIG[localProgress[child.id]?.[item.id] ?? null].border,
                        ]"
                        :title="item.name"
                      >
                        <span class="block text-base leading-none">
                          {{ STATUS_CONFIG[localProgress[child.id]?.[item.id] ?? null].icon || '―' }}
                        </span>
                        <span class="block text-xs leading-none mt-0.5">
                          {{ STATUS_CONFIG[localProgress[child.id]?.[item.id] ?? null].label }}
                        </span>
                      </button>
                    </td>
                  </tr>

                  <tr v-if="displayChildren.length === 0">
                    <td :colspan="2 + (selectedProgram?.items?.length ?? 0)" class="py-10 text-center text-gray-400">
                      契約中の児童がいません
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- 凡例 -->
            <div class="px-4 py-3 border-t bg-gray-50 flex gap-6 text-xs text-gray-500">
              <span v-for="(conf, key) in STATUS_CONFIG" :key="key" class="flex items-center gap-1.5">
                <span :class="['w-5 h-5 rounded border flex items-center justify-center text-xs font-bold', conf.bg, conf.text, conf.border]">
                  {{ conf.icon || '―' }}
                </span>
                {{ conf.label }}
              </span>
              <span class="ml-auto text-gray-400">※ セルをクリックするとステータスが切り替わります</span>
            </div>
          </div>
        </template>

      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
