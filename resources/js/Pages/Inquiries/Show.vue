<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  inquiry:             Object,
  statusLabels:        Object,
  categoryLabels:      Object,
  contactMethodLabels: Object,
})

const STATUS_COLOR = {
  open:        'bg-red-100 text-red-700',
  in_progress: 'bg-yellow-100 text-yellow-700',
  closed:      'bg-gray-100 text-gray-600',
}

const destroy = () => {
  if (confirm('この問い合わせを削除しますか？')) {
    Inertia.delete(route('inquiries.destroy', props.inquiry.id))
  }
}
</script>

<template>
  <Head :title="inquiry.subject || '問い合わせ詳細'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4 flex-wrap">
        <Link :href="route('inquiries.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">問い合わせ詳細</h2>
        <span :class="['text-xs font-medium px-2 py-1 rounded-full', STATUS_COLOR[inquiry.status]]">
          {{ statusLabels[inquiry.status] }}
        </span>
        <span v-if="inquiry.is_escalated" class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full">
          ⚠ エスカレーション
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="flex justify-end gap-2">
          <Link :href="route('inquiries.edit', inquiry.id)" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">編集</Link>
          <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50">削除</button>
        </div>

        <!-- メタ情報 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500">対象児童</dt>
              <dd class="font-medium">
                <Link :href="route('children.show', inquiry.child_id)" class="text-indigo-600 hover:underline">
                  {{ inquiry.child?.name }}
                </Link>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">問い合わせ日時</dt>
              <dd>{{ inquiry.contacted_at?.slice(0, 16).replace('T', ' ') }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">連絡手段</dt>
              <dd>{{ contactMethodLabels[inquiry.contact_method] }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">カテゴリ</dt>
              <dd>{{ categoryLabels[inquiry.category] }}</dd>
            </div>
            <div v-if="inquiry.staff">
              <dt class="text-xs text-gray-500">対応者</dt>
              <dd>{{ inquiry.staff?.name }}</dd>
            </div>
            <div v-if="inquiry.guardian">
              <dt class="text-xs text-gray-500">保護者</dt>
              <dd>{{ inquiry.guardian?.name }}</dd>
            </div>
          </dl>
        </div>

        <!-- 問い合わせ内容 -->
        <div class="bg-white shadow-sm rounded-lg p-5 space-y-4">
          <div v-if="inquiry.subject">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">件名</h3>
            <p class="text-sm font-medium text-gray-800">{{ inquiry.subject }}</p>
          </div>
          <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">問い合わせ内容</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-3 rounded">{{ inquiry.content }}</p>
          </div>
          <div v-if="inquiry.response">
            <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-1">回答・対応内容</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-green-50 p-3 rounded">{{ inquiry.response }}</p>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
