<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:       Object,
  certificate: Object,
})

const form = reactive({
  certificate_number:          props.certificate.certificate_number ?? '',
  municipality:                props.certificate.municipality ?? '',
  valid_from:                  props.certificate.valid_from ?? '',
  valid_to:                    props.certificate.valid_to ?? '',
  monthly_limit:               props.certificate.monthly_limit ?? 23,
  disability_support_category: props.certificate.disability_support_category ?? '',
  issue_date:                  props.certificate.issue_date ?? '',
  status:                      props.certificate.status ?? 'active',
})

const update = () => {
  Inertia.patch(
    route('children.certificates.update', { child: props.child.id, certificate: props.certificate.id }),
    form
  )
}

const destroy = () => {
  if (confirm('この受給者証を削除しますか？')) {
    Inertia.delete(
      route('children.certificates.destroy', { child: props.child.id, certificate: props.certificate.id })
    )
  }
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'

const STATUS_LABELS = { active: '有効', expired: '期限切れ', pending: '申請中' }
const statusColor = {
  active:  'bg-green-100 text-green-800',
  expired: 'bg-gray-100 text-gray-600',
  pending: 'bg-yellow-100 text-yellow-800',
}
</script>

<template>
  <Head :title="child.name + ' - 受給者証編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">
          ← {{ child.name }} へ戻る
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">受給者証 編集</h2>
        <span :class="['px-2 py-1 rounded-full text-xs font-medium', statusColor[certificate.status]]">
          {{ STATUS_LABELS[certificate.status] }}
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">受給者証番号</label>
                <input v-model="form.certificate_number" type="text" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">市区町村（交付元）</label>
                <input v-model="form.municipality" type="text" :class="inputClass" />
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
                <input v-model="form.disability_support_category" type="text" :class="inputClass" />
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

            <div class="flex justify-between pt-4 border-t">
              <button
                type="button"
                @click="destroy"
                class="px-4 py-2 text-sm border border-red-300 text-red-500 rounded hover:bg-red-50"
              >削除</button>
              <div class="flex gap-3">
                <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                  キャンセル
                </Link>
                <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                  更新する
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
