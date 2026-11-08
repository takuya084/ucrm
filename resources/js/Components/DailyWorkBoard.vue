<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ContactNoteStatus from '@/Components/ContactNoteStatus.vue'
const props = defineProps({ work: Object, date: String })
const selectedDate = ref(props.date)
const filter = ref('all')
const tasks = [
  { key: 'home', title: '家庭の連絡を確認', description: '未確認の連絡' },
  { key: 'record', title: '支援記録を入力', description: '出席児童の未入力記録' },
  { key: 'draft', title: '連絡帳の下書きを確認', description: '保護者へ未公開の連絡帳' },
  { key: 'delivery', title: '連携状況を確認', description: '待機中・確認が必要な連携' },
]
const rows = computed(() => props.work.rows.filter(row => filter.value === 'all' || row.tasks[filter.value]))
const changeDate = () => { if (selectedDate.value) router.get(route('dashboard'), { date: selectedDate.value }) }
</script>

<template>
  <section aria-label="日別の業務" class="space-y-5">
    <div class="bg-white rounded-xl border p-4 flex flex-wrap items-end gap-3">
      <label class="text-sm font-semibold">対象日<input type="date" v-model="selectedDate" @change="changeDate" class="block mt-1 border-gray-300 rounded-md" /></label>
      <Link :href="route('dashboard')" class="px-3 py-2 border rounded-md text-sm">今日へ</Link>
      <Link :href="route('usage-records.index', { date })" class="px-4 py-2 rounded-md bg-primary-600 text-white text-sm font-semibold">出欠・送迎を確認</Link>
      <p class="text-xs text-gray-600">日付を選び、残っている仕事から始めてください。</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3" aria-label="対応が必要な仕事">
      <button v-for="task in tasks" :key="task.key" type="button" @click="filter = task.key" :aria-pressed="filter === task.key"
        :class="['text-left rounded-xl border p-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600', filter === task.key ? 'bg-primary-50 border-primary-500' : 'bg-white hover:border-primary-400']">
        <span class="block text-xs text-gray-600">{{ task.description }}</span>
        <span class="block text-2xl font-bold mt-1">{{ work.counts[task.key] }}<span class="text-sm ml-1">件</span></span>
        <span class="block text-sm font-semibold text-primary-700 mt-2">{{ task.title }} →</span>
      </button>
    </div>
    <div class="rounded-xl border bg-white overflow-hidden">
      <div class="p-4 border-b flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-bold">{{ date }} の児童と仕事</h3>
        <button type="button" @click="filter = 'all'" :aria-pressed="filter === 'all'" class="text-sm text-primary-700 underline px-2 py-1">すべて表示</button>
      </div>
      <p v-if="!work.hasAttendance" class="m-4 p-3 bg-blue-50 text-blue-900 text-sm rounded-md">出欠はまだ確定していません。「出欠・送迎を確認」で予約・利用予定を確認し、対象児童を確定してください。家庭からの連絡は先に確認できます。</p>
      <p v-if="!rows.length" class="p-6 text-sm text-gray-600">{{ filter === 'all' ? 'この日に確定した利用記録・届いた連絡帳はありません。' : 'この条件で対応が必要な児童はいません。' }}</p>
      <ul v-else class="divide-y">
        <li v-for="row in rows" :key="row.child_id" class="p-4 grid grid-cols-1 xl:grid-cols-[1fr_1fr_1.4fr] gap-4" :aria-label="row.child_name + 'の業務'">
          <div>
            <Link :href="route('children.show', row.child_id)" class="font-bold text-primary-800 underline">{{ row.child_name }}</Link>
            <p class="text-sm mt-1">{{ row.attendance }} · {{ row.transport }}</p>
            <p class="text-sm text-gray-600 mt-1">支援記録：{{ row.record }}</p>
          </div>
          <div>
            <p :class="['text-sm font-semibold mb-2', row.state.homePending ? 'text-amber-800' : 'text-gray-600']">{{ row.state.home }}</p>
            <Link :href="row.note_url" class="text-sm text-primary-700 underline">家庭の連絡・公開内容を確認 →</Link>
          </div>
          <div class="space-y-3">
            <ContactNoteStatus :state="row.state" />
            <Link v-if="row.record_url" :href="row.record_url" class="inline-block rounded-md bg-primary-600 text-white px-4 py-2 text-sm">{{ row.record === '保存済み' ? '記録・連絡帳を編集' : '記録・連絡帳を入力' }}</Link>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
