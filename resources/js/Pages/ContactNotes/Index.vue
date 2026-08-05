<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  date:         String,
  rows:         Array,
  statusLabels: Object,
  exportYears:  Array,
  children:     Array,
})

// 年間PDF出力（年末の保存運用）
const exportYear    = ref(props.exportYears?.[0] ?? String(new Date().getFullYear()))
const exportChildId = ref(null)

const exportChildUrl = computed(() =>
  exportChildId.value
    ? route('contact-notes.export-yearly', { year: exportYear.value, child_id: exportChildId.value })
    : null
)
const exportZipUrl = computed(() =>
  route('contact-notes.export-yearly-zip', { year: exportYear.value })
)

const selectedDate = ref(props.date)
const changeDate = () => {
  router.visit(route('contact-notes.index', { date: selectedDate.value }))
}
const moveDay = (diff) => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() + diff)
  selectedDate.value = d.toISOString().slice(0, 10)
  changeDate()
}

const summary = computed(() => ({
  total:     props.rows.length,
  published: props.rows.filter(r => r.note?.status === 'published').length,
  draft:     props.rows.filter(r => r.note?.status === 'draft').length,
  none:      props.rows.filter(r => !r.note).length,
  read:      props.rows.filter(r => r.note?.read_at).length,
  homeEntry: props.rows.filter(r => r.note?.guardian_submitted_at).length,
}))

const publish = (row) => {
  if (!confirm(`${row.child_name} さんの連絡帳を保護者に公開します。よろしいですか？`)) return
  router.post(route('contact-notes.publish', row.note.id), {}, { preserveScroll: true })
}

const fmtTime = (v) => v ? v.replace('T', ' ').slice(11, 16) : ''
</script>

<template>
  <Head title="連絡帳" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">連絡帳</h2>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 日付ナビ -->
        <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center gap-3 flex-wrap">
          <button @click="moveDay(-1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50">← 前日</button>
          <input v-model="selectedDate" type="date" @change="changeDate"
            class="border border-gray-300 rounded-md px-3 py-1.5 text-sm" />
          <button @click="moveDay(1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50">翌日 →</button>
          <Link :href="route('usage-records.index', { date: selectedDate })"
            class="ml-auto text-sm text-primary-600 hover:text-primary-800">出席管理へ →</Link>
        </div>

        <!-- サマリー -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div class="bg-white rounded-lg border border-gray-200 p-3 text-center">
            <div class="text-xl font-bold text-gray-700">{{ summary.total }}</div>
            <div class="text-xs text-gray-500">対象児童</div>
          </div>
          <div class="bg-green-50 rounded-lg shadow-sm p-3 text-center">
            <div class="text-xl font-bold text-green-700">{{ summary.published }}</div>
            <div class="text-xs text-gray-500">公開済み</div>
          </div>
          <div class="bg-gray-50 rounded-lg shadow-sm p-3 text-center">
            <div class="text-xl font-bold text-gray-500">{{ summary.draft }}</div>
            <div class="text-xs text-gray-500">下書き</div>
          </div>
          <div class="bg-blue-50 rounded-lg shadow-sm p-3 text-center">
            <div class="text-xl font-bold text-blue-700">{{ summary.read }}</div>
            <div class="text-xs text-gray-500">保護者既読</div>
          </div>
          <div class="bg-amber-50 rounded-lg shadow-sm p-3 text-center">
            <div class="text-xl font-bold text-amber-700">{{ summary.homeEntry }}</div>
            <div class="text-xs text-gray-500">家庭記入あり</div>
          </div>
        </div>

        <!-- 一覧 -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div v-if="rows.length === 0" class="py-12 text-center text-gray-400">
            この日の対象児童がいません
          </div>
          <div v-else class="divide-y">
            <div v-for="row in rows" :key="row.child_id" class="p-4 flex items-center gap-3 flex-wrap">
              <!-- 児童名 -->
              <div class="w-40">
                <Link :href="route('children.show', row.child_id)"
                  class="font-medium text-gray-900 hover:text-primary-600 text-sm">{{ row.child_name }}</Link>
              </div>

              <!-- 状態バッジ -->
              <div class="flex items-center gap-2 flex-wrap">
                <span v-if="!row.note" class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-400">未作成</span>
                <template v-else>
                  <span :class="['text-xs px-2 py-1 rounded-full border',
                    row.note.status === 'published'
                      ? 'bg-green-50 text-green-600 border-green-200'
                      : 'bg-gray-50 text-gray-500 border-gray-200']">
                    {{ statusLabels[row.note.status] ?? row.note.status }}
                  </span>
                  <span v-if="row.note.read_at" class="text-xs px-2 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-200">
                    ✓ 既読 {{ fmtTime(row.note.read_at) }}
                  </span>
                  <span v-if="row.note.guardian_submitted_at" class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-600 border border-amber-200">
                    家庭記入あり
                  </span>
                </template>
              </div>

              <!-- メッセージ冒頭プレビュー -->
              <p v-if="row.note?.guardian_message" class="hidden sm:block flex-1 text-xs text-gray-400 truncate min-w-0">
                {{ row.note.guardian_message }}
              </p>

              <!-- 操作 -->
              <div class="ml-auto flex items-center gap-2">
                <button
                  v-if="row.note && row.note.status === 'draft' && (row.note.guardian_message || row.note.meal_note || row.note.health_note)"
                  @click="publish(row)"
                  class="text-xs px-3 py-1.5 bg-green-500 text-white rounded-md hover:bg-green-600"
                >公開する</button>
                <Link
                  v-if="row.note?.support_record_id"
                  :href="route('support-records.show', row.note.support_record_id)"
                  class="text-xs px-3 py-1.5 border border-primary-300 text-primary-600 rounded-md hover:bg-primary-50"
                >記録を見る</Link>
                <Link
                  v-else-if="row.usage_record_id"
                  :href="route('support-records.create', { usage_record_id: row.usage_record_id })"
                  class="text-xs px-3 py-1.5 bg-green-500 text-white rounded-md hover:bg-green-600"
                >記録・連絡帳を書く</Link>
              </div>
            </div>
          </div>
        </div>

        <!-- 年間PDF出力（年末の保存運用。要配慮個人情報の一括出力のためリーダー以上のみ） -->
        <div v-if="['admin', 'leader'].includes($page.props.auth.staff_role)" class="bg-white border border-gray-200 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">年間PDF出力（記録の保存用）</h3>
          <div class="flex items-center gap-3 flex-wrap">
            <select v-model="exportYear" class="border border-gray-300 rounded-md px-3 py-1.5 text-sm">
              <option v-for="y in exportYears" :key="y" :value="y">{{ y }}年</option>
            </select>
            <select v-model="exportChildId" class="border border-gray-300 rounded-md px-3 py-1.5 text-sm">
              <option :value="null">― 児童を選択 ―</option>
              <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <a
              v-if="exportChildUrl"
              :href="exportChildUrl"
              class="px-4 py-1.5 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600"
            >この児童の1年分をPDF出力</a>
            <span v-else class="px-4 py-1.5 text-sm text-gray-300 border border-gray-200 rounded-md cursor-not-allowed">
              この児童の1年分をPDF出力
            </span>
            <a
              :href="exportZipUrl"
              class="px-4 py-1.5 text-sm text-primary-600 border border-primary-300 rounded-md hover:bg-primary-50"
            >全児童分を一括出力（ZIP）</a>
          </div>
          <p class="text-xs text-gray-400 mt-2">
            指定した年の1月〜12月の連絡帳（未公開の下書き・家庭からの連絡を含む全記録）をPDFにまとめます。出力は監査ログに記録されます。
          </p>
        </div>

        <p class="text-xs text-gray-400">
          連絡帳は支援記録の入力画面から記入します。公開すると保護者アプリ（予約システム）に配信されます。
        </p>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
