<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  record: Object,
})

const CONDITION = {
  good:   { label: '良好', class: 'bg-green-100 text-green-700' },
  normal: { label: '普通', class: 'bg-blue-100  text-blue-700'  },
  poor:   { label: '不調', class: 'bg-red-100   text-red-700'  },
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
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', record.child_id)" class="text-gray-400 hover:text-gray-600 text-sm">
          ← {{ record.child?.name }}
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">支援記録 — {{ record.date }}</h2>
        <span :class="['px-2 py-1 rounded-full text-xs font-medium', CONDITION[record.condition]?.class]">
          {{ CONDITION[record.condition]?.label }}
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="flex justify-end">
          <Link
            :href="route('support-records.edit', record.id)"
            class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600"
          >編集</Link>
        </div>

        <!-- 実施プログラム -->
        <div v-if="record.programs?.length" class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">実施プログラム</h3>
          <div class="space-y-3">
            <div v-for="p in record.programs" :key="p.id">
              <!-- プログラム名 + 時間 -->
              <div class="flex items-center gap-2 flex-wrap">
                <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium">
                  {{ p.name }}
                </span>
                <span v-if="p.pivot?.duration_minutes" class="text-xs text-indigo-500">
                  {{ p.pivot.duration_minutes }}分
                </span>
              </div>
              <!-- 詳細項目 -->
              <div v-if="p.selected_items?.length" class="mt-1.5 ml-2 flex flex-wrap gap-1.5">
                <span
                  v-for="item in p.selected_items"
                  :key="item.id"
                  class="px-2 py-0.5 bg-indigo-50 border border-indigo-200 text-indigo-600 rounded text-xs"
                >
                  {{ item.name }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- 記録本文 -->
        <div class="bg-white shadow-sm rounded-lg p-5 space-y-4">
          <div v-if="record.behavior_note">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">行動・様子</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ record.behavior_note }}</p>
          </div>
          <div v-if="record.achievement_note">
            <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-1">成功体験・できたこと</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-green-50 p-2 rounded">{{ record.achievement_note }}</p>
          </div>
          <div v-if="record.challenge_note">
            <h3 class="text-xs font-semibold text-orange-500 uppercase tracking-wide mb-1">課題・気になること</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-orange-50 p-2 rounded">{{ record.challenge_note }}</p>
          </div>
          <div v-if="record.next_action">
            <h3 class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">次回への申し送り</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-indigo-50 p-2 rounded">{{ record.next_action }}</p>
          </div>
          <div v-if="record.care_note">
            <h3 class="text-xs font-semibold text-yellow-600 uppercase tracking-wide mb-1">本日の配慮メモ</h3>
            <p class="text-sm text-gray-800">{{ record.care_note }}</p>
          </div>
        </div>

        <!-- メタ情報 -->
        <div class="bg-white shadow-sm rounded-lg p-4 text-xs text-gray-500 flex gap-4">
          <span>記録者：{{ record.staff?.name ?? '―' }}</span>
          <span v-if="record.is_shared_with_guardian" class="text-blue-600 font-medium">📋 保護者共有済</span>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
