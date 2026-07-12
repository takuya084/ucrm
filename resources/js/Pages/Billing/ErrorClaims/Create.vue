<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ billingDetail: Object })
const fmt = (n) => Number(n).toLocaleString()

const form = ref({
  billing_detail_id: props.billingDetail.id,
  claim_type: 'full_cancel',
  reason: '',
})

const submit = () => {
  router.post(route('billing.error-claims.store'), form.value)
}
</script>

<template>
  <Head title="過誤申立作成" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">過誤申立作成</h2>
        <Link :href="route('billing.error-claims.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">戻る</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <!-- 対象明細情報 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">対象請求明細</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div><span class="text-xs text-gray-500 block">児童名</span>{{ billingDetail.child?.name }}</div>
            <div><span class="text-xs text-gray-500 block">年月</span>{{ billingDetail.billing_period?.year_month }}</div>
            <div><span class="text-xs text-gray-500 block">費用合計</span>{{ fmt(billingDetail.total_amount) }}円</div>
          </div>
        </div>

        <!-- 申立フォーム -->
        <div class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">申立内容</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">申立種別</label>
              <select v-model="form.claim_type" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm">
                <option value="full_cancel">全額取消</option>
                <option value="partial_correction">一部修正</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">理由</label>
              <textarea v-model="form.reason" rows="4" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" placeholder="過誤の理由を記入してください"></textarea>
            </div>
            <div class="flex justify-end">
              <button @click="submit" :disabled="!form.reason" class="px-6 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition disabled:opacity-50">
                作成
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
