<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  record:             Object,
  recordDate:         String,
  contactNote:        Object,
  shortTermGoal:      String,
  fiveDomainLabels:   Object,
  goalProgressLabels: Object,
})

const publishNote = () => {
  if (!confirm('連絡帳を保護者に公開します。よろしいですか？')) return
  router.post(route('contact-notes.publish', props.contactNote.id), {}, { preserveScroll: true })
}

const fmtDateTime = (v) => v ? v.replace('T', ' ').slice(0, 16) : null

const CONDITION = {
  good:   { label: '良好', class: 'bg-green-100 text-green-700 border-green-200' },
  normal: { label: '普通', class: 'bg-blue-100  text-blue-700  border-blue-200'  },
  poor:   { label: '不調', class: 'bg-red-100   text-red-700   border-red-200'  },
}

const CATEGORY_LABELS = {
  physical: '運動', cognitive: '認知・学習', social: '社会性・SST',
  life_skills: '生活スキル', other: 'その他',
}
</script>

<template>
  <Head :title="record.child?.name + ' - 支援記録'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">支援記録</h2>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- ヘッダーカード：児童名・日付・状態・操作 -->
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-4">
              <div>
                <h3 class="text-lg font-bold text-gray-800">{{ record.child?.name }}</h3>
                <p class="text-sm text-gray-500">{{ recordDate }}</p>
              </div>
              <span :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-bold border', CONDITION[record.condition]?.class]">
                {{ CONDITION[record.condition]?.label ?? '未設定' }}
              </span>
              <span v-if="contactNote"
                :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border',
                  contactNote.status === 'published'
                    ? 'bg-green-50 text-green-600 border-green-200'
                    : 'bg-gray-50 text-gray-500 border-gray-200']">
                連絡帳{{ contactNote.status === 'published' ? '公開済み' : '（下書き）' }}
              </span>
            </div>
            <div class="flex items-center gap-3">
              <Link
                :href="route('usage-records.index', { date: recordDate })"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              >← 出席管理に戻る</Link>
              <Link
                :href="route('support-records.edit', record.id)"
                class="px-4 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600"
              >編集</Link>
            </div>
          </div>
        </div>

        <!-- メインコンテンツ 2カラム -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

          <!-- 左カラム：記録本文 -->
          <div class="lg:col-span-2 space-y-4">

            <!-- 行動・様子 -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-gray-400 rounded-full inline-block"></span>
                行動・様子
              </h3>
              <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ record.behavior_note || '―' }}</p>
            </div>

            <!-- 成功体験 -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
              <h3 class="text-sm font-semibold text-green-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-green-400 rounded-full inline-block"></span>
                成功体験・できたこと
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded-md px-3 py-2', record.achievement_note ? 'text-gray-800 bg-green-50' : 'text-gray-400']">
                {{ record.achievement_note || '―' }}
              </p>
            </div>

            <!-- 課題 -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
              <h3 class="text-sm font-semibold text-orange-600 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-orange-400 rounded-full inline-block"></span>
                課題・気になること
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded-md px-3 py-2', record.challenge_note ? 'text-gray-800 bg-orange-50' : 'text-gray-400']">
                {{ record.challenge_note || '―' }}
              </p>
            </div>

            <!-- 次回への申し送り -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
              <h3 class="text-sm font-semibold text-primary-600 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-primary-400 rounded-full inline-block"></span>
                次回への申し送り
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded-md px-3 py-2', record.next_action ? 'text-gray-800 bg-primary-50' : 'text-gray-400']">
                {{ record.next_action || '―' }}
              </p>
            </div>

            <!-- 連絡帳（保護者に公開される内容） -->
            <div v-if="contactNote" class="bg-white rounded-lg border-2 border-green-200 overflow-hidden">
              <div class="flex items-center justify-between px-5 py-3 bg-green-50 border-b border-green-200">
                <h3 class="text-sm font-semibold text-green-700">連絡帳（保護者に公開される内容）</h3>
                <div class="flex items-center gap-2">
                  <span v-if="contactNote.status === 'published'" class="text-xs text-green-600">
                    {{ fmtDateTime(contactNote.published_at) }} 公開
                    <span v-if="contactNote.read_at" class="ml-1 font-bold">✓ 既読</span>
                  </span>
                  <button
                    v-else
                    @click="publishNote"
                    class="px-3 py-1.5 text-xs text-white bg-green-500 rounded-md hover:bg-green-600"
                  >保護者に公開する</button>
                </div>
              </div>
              <div class="p-5 space-y-3">
                <div v-if="contactNote.guardian_submitted_at" class="p-3 bg-amber-50 border border-amber-200 rounded-md">
                  <p class="text-xs font-bold text-amber-700 mb-1">家庭からの連絡</p>
                  <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-amber-800 mb-1">
                    <span v-if="contactNote.home_temperature">体温：{{ contactNote.home_temperature }}℃</span>
                    <span v-if="contactNote.home_sleep">睡眠：{{ contactNote.home_sleep }}</span>
                    <span v-if="contactNote.home_medication">服薬：{{ contactNote.home_medication }}</span>
                    <span v-if="contactNote.home_condition">朝の様子：{{ contactNote.home_condition }}</span>
                  </div>
                  <p v-if="contactNote.guardian_comment" class="text-sm text-amber-900 whitespace-pre-wrap">{{ contactNote.guardian_comment }}</p>
                </div>
                <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ contactNote.guardian_message || '（メッセージ未記入）' }}</p>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600">
                  <span v-if="contactNote.meal_note">食事: {{ contactNote.meal_note }}</span>
                  <span v-if="contactNote.health_note">体調: {{ contactNote.health_note }}</span>
                </div>
                <div v-if="contactNote.five_domain_tags?.length || contactNote.goal_progress" class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-gray-100">
                  <span
                    v-for="tag in contactNote.five_domain_tags ?? []" :key="tag"
                    class="px-2 py-0.5 bg-green-50 border border-green-200 text-green-600 rounded-full text-xs"
                  >{{ fiveDomainLabels?.[tag] ?? tag }}</span>
                  <span v-if="contactNote.goal_progress" class="px-2 py-0.5 bg-gray-50 border border-gray-200 text-gray-600 rounded-full text-xs">
                    目標: {{ goalProgressLabels?.[contactNote.goal_progress] ?? contactNote.goal_progress }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- 右カラム：プログラム・メタ情報 -->
          <div class="space-y-4">

            <!-- 実施プログラム -->
            <div class="bg-white border border-gray-200 rounded-lg p-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="w-1 h-4 bg-primary-400 rounded-full inline-block"></span>
                実施プログラム
              </h3>
              <div v-if="record.programs?.length" class="space-y-3">
                <div v-for="p in record.programs" :key="p.id"
                  class="p-3 bg-primary-50 border border-primary-100 rounded-lg">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-primary-700">{{ p.name }}</span>
                    <span v-if="p.pivot?.duration_minutes" class="text-xs text-primary-500 bg-white px-2 py-0.5 rounded-full">
                      {{ p.pivot.duration_minutes }}分
                    </span>
                  </div>
                  <div v-if="p.selected_items?.length" class="mt-2 flex flex-wrap gap-1.5">
                    <span
                      v-for="item in p.selected_items"
                      :key="item.id"
                      class="px-2 py-0.5 bg-white border border-primary-200 text-primary-600 rounded-full text-xs"
                    >{{ item.name }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">プログラム未選択</p>
            </div>

            <!-- 配慮メモ -->
            <div v-if="record.care_note" class="bg-amber-50 shadow-sm rounded-lg p-5 border border-amber-200">
              <h3 class="text-sm font-semibold text-amber-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-amber-400 rounded-full inline-block"></span>
                本日の配慮メモ
              </h3>
              <p class="text-sm text-amber-800 leading-relaxed">{{ record.care_note }}</p>
            </div>

            <!-- 記録者 -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
              <div class="text-xs text-gray-500 space-y-1">
                <div class="flex justify-between">
                  <span>記録者</span>
                  <span class="text-gray-700 font-medium">{{ record.staff?.name ?? '―' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
