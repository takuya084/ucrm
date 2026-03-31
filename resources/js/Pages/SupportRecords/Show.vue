<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  record:     Object,
  recordDate: String,
})

const CONDITION = {
  good:   { label: '良好', icon: '😊', class: 'bg-green-100 text-green-700 border-green-200' },
  normal: { label: '普通', icon: '🙂', class: 'bg-blue-100  text-blue-700  border-blue-200'  },
  poor:   { label: '不調', icon: '😔', class: 'bg-red-100   text-red-700   border-red-200'  },
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
        <div class="bg-white shadow-sm rounded-lg p-5">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-4">
              <div>
                <h3 class="text-lg font-bold text-gray-800">{{ record.child?.name }}</h3>
                <p class="text-sm text-gray-500">{{ recordDate }}</p>
              </div>
              <span :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-bold border', CONDITION[record.condition]?.class]">
                <span>{{ CONDITION[record.condition]?.icon }}</span>
                {{ CONDITION[record.condition]?.label ?? '未設定' }}
              </span>
              <span v-if="record.is_shared_with_guardian"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600 border border-blue-200">
                連絡帳記載済
              </span>
            </div>
            <div class="flex items-center gap-3">
              <Link
                :href="route('usage-records.index', { date: recordDate })"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
              >← 出席管理に戻る</Link>
              <Link
                :href="route('support-records.edit', record.id)"
                class="px-4 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600"
              >編集</Link>
            </div>
          </div>
        </div>

        <!-- メインコンテンツ 2カラム -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

          <!-- 左カラム：記録本文 -->
          <div class="lg:col-span-2 space-y-4">

            <!-- 行動・様子 -->
            <div class="bg-white shadow-sm rounded-lg p-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-gray-400 rounded-full inline-block"></span>
                行動・様子
              </h3>
              <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ record.behavior_note || '―' }}</p>
            </div>

            <!-- 成功体験 -->
            <div class="bg-white shadow-sm rounded-lg p-5">
              <h3 class="text-sm font-semibold text-green-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-green-400 rounded-full inline-block"></span>
                成功体験・できたこと
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded px-3 py-2', record.achievement_note ? 'text-gray-800 bg-green-50' : 'text-gray-400']">
                {{ record.achievement_note || '―' }}
              </p>
            </div>

            <!-- 課題 -->
            <div class="bg-white shadow-sm rounded-lg p-5">
              <h3 class="text-sm font-semibold text-orange-600 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-orange-400 rounded-full inline-block"></span>
                課題・気になること
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded px-3 py-2', record.challenge_note ? 'text-gray-800 bg-orange-50' : 'text-gray-400']">
                {{ record.challenge_note || '―' }}
              </p>
            </div>

            <!-- 次回への申し送り -->
            <div class="bg-white shadow-sm rounded-lg p-5">
              <h3 class="text-sm font-semibold text-indigo-600 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-indigo-400 rounded-full inline-block"></span>
                次回への申し送り
              </h3>
              <p :class="['text-sm whitespace-pre-wrap leading-relaxed rounded px-3 py-2', record.next_action ? 'text-gray-800 bg-indigo-50' : 'text-gray-400']">
                {{ record.next_action || '―' }}
              </p>
            </div>
          </div>

          <!-- 右カラム：プログラム・メタ情報 -->
          <div class="space-y-4">

            <!-- 実施プログラム -->
            <div class="bg-white shadow-sm rounded-lg p-5">
              <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="w-1 h-4 bg-indigo-400 rounded-full inline-block"></span>
                実施プログラム
              </h3>
              <div v-if="record.programs?.length" class="space-y-3">
                <div v-for="p in record.programs" :key="p.id"
                  class="p-3 bg-indigo-50 border border-indigo-100 rounded-lg">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-indigo-700">{{ p.name }}</span>
                    <span v-if="p.pivot?.duration_minutes" class="text-xs text-indigo-500 bg-white px-2 py-0.5 rounded-full">
                      {{ p.pivot.duration_minutes }}分
                    </span>
                  </div>
                  <div v-if="p.selected_items?.length" class="mt-2 flex flex-wrap gap-1.5">
                    <span
                      v-for="item in p.selected_items"
                      :key="item.id"
                      class="px-2 py-0.5 bg-white border border-indigo-200 text-indigo-600 rounded-full text-xs"
                    >{{ item.name }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">プログラム未選択</p>
            </div>

            <!-- 配慮メモ -->
            <div v-if="record.care_note" class="bg-yellow-50 shadow-sm rounded-lg p-5 border border-yellow-200">
              <h3 class="text-sm font-semibold text-yellow-700 mb-2 flex items-center gap-2">
                <span class="w-1 h-4 bg-yellow-400 rounded-full inline-block"></span>
                本日の配慮メモ
              </h3>
              <p class="text-sm text-yellow-800 leading-relaxed">{{ record.care_note }}</p>
            </div>

            <!-- 記録者 -->
            <div class="bg-white shadow-sm rounded-lg p-4">
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
