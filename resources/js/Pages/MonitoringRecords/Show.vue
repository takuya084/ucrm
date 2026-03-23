<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:  Object,
  record: Object,
})

const destroy = () => {
  if (confirm('このモニタリング記録を削除しますか？')) {
    Inertia.delete(route('children.monitoring.destroy', [props.child.id, props.record.id]))
  }
}
</script>

<template>
  <Head :title="child.name + ' - モニタリング記録'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">
          モニタリング記録 — {{ record.monitoring_date }}
        </h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="flex justify-end gap-2">
          <Link
            :href="route('children.monitoring.edit', [child.id, record.id])"
            class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600"
          >編集</Link>
          <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50">削除</button>
        </div>

        <!-- メタ情報 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500">実施日</dt>
              <dd class="font-medium">{{ record.monitoring_date }}</dd>
            </div>
            <div v-if="record.period_from || record.period_to">
              <dt class="text-xs text-gray-500">対象期間</dt>
              <dd>{{ record.period_from }} 〜 {{ record.period_to }}</dd>
            </div>
            <div v-if="record.next_review_date">
              <dt class="text-xs text-gray-500">次回予定日</dt>
              <dd>{{ record.next_review_date }}</dd>
            </div>
            <div v-if="record.staff">
              <dt class="text-xs text-gray-500">記録者</dt>
              <dd>{{ record.staff?.name }}</dd>
            </div>
          </dl>
        </div>

        <!-- 記録本文 -->
        <div class="bg-white shadow-sm rounded-lg p-5 space-y-4">
          <div v-if="record.support_summary">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">支援の経過まとめ</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ record.support_summary }}</p>
          </div>
          <div v-if="record.strengths">
            <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-1">強み・できるようになったこと</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-green-50 p-3 rounded">{{ record.strengths }}</p>
          </div>
          <div v-if="record.challenges">
            <h3 class="text-xs font-semibold text-orange-500 uppercase tracking-wide mb-1">課題・継続支援が必要なこと</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-orange-50 p-3 rounded">{{ record.challenges }}</p>
          </div>
          <div v-if="record.guardian_needs">
            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">保護者のニーズ・希望</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-blue-50 p-3 rounded">{{ record.guardian_needs }}</p>
          </div>
          <div v-if="record.environmental_notes">
            <h3 class="text-xs font-semibold text-purple-600 uppercase tracking-wide mb-1">環境・家庭状況</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-purple-50 p-3 rounded">{{ record.environmental_notes }}</p>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
