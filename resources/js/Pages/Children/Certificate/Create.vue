<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:              Object,
  externalFacilities: { type: Array, default: () => [] },
})

const form = reactive({
  certificate_number:          '',
  municipality:                '',
  valid_from:                  '',
  valid_to:                    '',
  monthly_limit:               23,
  disability_support_category: '',
  issue_date:                  '',
  status:                      'active',
  copayment_rate:              10,
  copayment_cap_monthly:       '',
  is_cap_management_target:    false,
  service_type:                '',
  municipality_code:           '',
  external_facility_ids:       [],
})

const store = () => {
  Inertia.post(route('children.certificates.store', props.child.id), form)
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - 受給者証登録'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">
          ← {{ child.name }} へ戻る
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">受給者証 登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <!-- 注意書き -->
          <div class="mb-6 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700">
            ステータスを「有効」にすると、既存の有効な受給者証は自動的に「期限切れ」になります。
          </div>

          <form @submit.prevent="store" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">受給者証番号</label>
                <input v-model="form.certificate_number" type="text" :class="inputClass" placeholder="例：1234567890" />
              </div>
              <div>
                <label :class="labelClass">市区町村（交付元）</label>
                <input v-model="form.municipality" type="text" :class="inputClass" placeholder="例：東京都渋谷区" />
              </div>
              <div>
                <label :class="labelClass">有効期間（開始）</label>
                <input v-model="form.valid_from" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">有効期間（終了）</label>
                <input v-model="form.valid_to" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">月あたり支給量（回） <span class="text-red-500">*</span></label>
                <input v-model="form.monthly_limit" type="number" min="1" max="31" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">通所支援種別</label>
                <input v-model="form.disability_support_category" type="text" :class="inputClass" placeholder="例：放課後等デイサービス" />
              </div>
              <div>
                <label :class="labelClass">交付日</label>
                <input v-model="form.issue_date" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">ステータス <span class="text-red-500">*</span></label>
                <select v-model="form.status" :class="inputClass">
                  <option value="active">有効</option>
                  <option value="pending">申請中</option>
                  <option value="expired">期限切れ</option>
                </select>
              </div>
            </div>

            <!-- 請求設定 -->
            <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mt-2">請求設定</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">サービス種別</label>
                <select v-model="form.service_type" :class="inputClass">
                  <option value="">未設定</option>
                  <option value="houday">放課後等デイサービス</option>
                  <option value="jidou">児童発達支援</option>
                </select>
              </div>
              <div>
                <label :class="labelClass">市区町村コード（6桁）</label>
                <input v-model="form.municipality_code" type="text" maxlength="6" :class="inputClass" placeholder="例：131130" />
              </div>
              <div>
                <label :class="labelClass">自己負担割合（%）</label>
                <input v-model.number="form.copayment_rate" type="number" min="0" max="100" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">上限月額（円）</label>
                <input v-model.number="form.copayment_cap_monthly" type="number" min="0" :class="inputClass" placeholder="例：4600" />
                <p class="text-xs text-gray-400 mt-1">非課税世帯=0、低所得=4,600、一般=37,200</p>
              </div>
              <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="form.is_cap_management_target" type="checkbox" class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-300" />
                  上限管理対象（複数事業所利用の場合にチェック）
                </label>
              </div>
            </div>

            <!-- 併給先（他社事業所） -->
            <div v-if="form.is_cap_management_target">
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mt-2">併給先（他社事業所）</h3>
              <p class="text-xs text-gray-500 mt-2 mb-3">
                上限管理対象時、他社事業所の利用がある場合に選択してください。
              </p>
              <div v-if="externalFacilities.length === 0" class="text-sm text-gray-400 py-2">
                他社事業所が未登録です。
                <Link :href="route('external-facilities.create')" class="text-indigo-500 hover:underline">登録する</Link>
              </div>
              <div v-else class="space-y-2">
                <label
                  v-for="ef in externalFacilities"
                  :key="ef.id"
                  class="flex items-center gap-3 p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="ef.id"
                    v-model="form.external_facility_ids"
                    class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-300"
                  />
                  <span class="text-sm">
                    <span class="font-medium text-gray-800">{{ ef.name }}</span>
                    <span class="ml-2 text-xs text-gray-400 font-mono">{{ ef.facility_number }}</span>
                  </span>
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                登録する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
