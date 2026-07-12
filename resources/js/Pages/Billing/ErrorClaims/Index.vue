<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({ claims: Object })
const fmt = (n) => Number(n).toLocaleString()

const TYPE_LABEL = { full_cancel: '全額取消', partial_correction: '一部修正' }
const STATUS_COLOR = {
  draft: 'bg-gray-100 text-gray-600', submitted: 'bg-blue-100 text-blue-700',
  accepted: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700',
}
const STATUS_LABEL = { draft: '下書き', submitted: '提出済', accepted: '受理', rejected: '却下' }
</script>

<template>
  <Head title="過誤申立" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">過誤申立</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
          <a :href="route('billing.error-claims.export')" class="px-4 py-1.5 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition">CSV出力</a>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="claims.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
            過誤申立がありません
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">児童名</th>
                <th class="px-4 py-2 text-left">対象年月</th>
                <th class="px-4 py-2 text-left">種別</th>
                <th class="px-4 py-2 text-left">ステータス</th>
                <th class="px-4 py-2 text-left">理由</th>
                <th class="px-4 py-2 text-left">作成日</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="claim in claims.data" :key="claim.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ claim.child?.name }}</td>
                <td class="px-4 py-3 text-xs">{{ claim.original_year_month }}</td>
                <td class="px-4 py-3 text-xs">{{ TYPE_LABEL[claim.claim_type] }}</td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[claim.status]]">
                    {{ STATUS_LABEL[claim.status] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600 max-w-xs truncate">{{ claim.reason }}</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ claim.created_at?.slice(0, 10) }}</td>
              </tr>
            </tbody>
          </table>

          <div v-if="claims.last_page > 1" class="px-5 py-3 border-t flex gap-2 text-sm">
            <Link v-for="link in claims.links" :key="link.label" :href="link.url ?? '#'" v-html="link.label"
              :class="['px-3 py-1 border rounded', link.active ? 'bg-indigo-500 text-white border-indigo-500' : 'border-gray-300 text-gray-600 hover:bg-gray-50', !link.url ? 'opacity-40 pointer-events-none' : '']" />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
