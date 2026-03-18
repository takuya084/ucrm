<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:          Object,
  registeredDays: Array,  // 登録済み曜日（選択不可にする）
})

const DAY_OPTIONS = [
  { value: 'mon', label: '月曜日' },
  { value: 'tue', label: '火曜日' },
  { value: 'wed', label: '水曜日' },
  { value: 'thu', label: '木曜日' },
  { value: 'fri', label: '金曜日' },
  { value: 'sat', label: '土曜日' },
]

const STATUS_OPTIONS = [
  { value: 'regular',   label: '定期利用' },
  { value: 'temporary', label: '一時利用' },
  { value: 'trial',     label: '体験利用' },
]

const today = new Date().toISOString().slice(0, 10)

const form = reactive({
  day_of_week:     '',
  start_date:      today,
  end_date:        '',
  status:          'regular',
  pickup_required: false,
  memo:            '',
})

const store = () => {
  Inertia.post(route('children.schedules.store', props.child.id), form)
}

const isRegistered = (day) => props.registeredDays.includes(day)

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - 利用曜日登録'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">
          ← {{ child.name }} へ戻る
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">利用曜日 登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <!-- 登録済み曜日の案内 -->
          <div v-if="registeredDays.length" class="mb-5 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-700">
            <span class="font-medium">登録済み：</span>
            {{ registeredDays.map(d => ({ mon:'月',tue:'火',wed:'水',thu:'木',fri:'金',sat:'土' })[d]).join('・') }}曜日
          </div>

          <form @submit.prevent="store" class="space-y-5">

            <!-- 曜日選択 -->
            <div>
              <label :class="labelClass">利用曜日 <span class="text-red-500">*</span></label>
              <div class="grid grid-cols-3 gap-2 mt-1">
                <label
                  v-for="opt in DAY_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'flex items-center justify-center gap-2 px-3 py-3 border rounded cursor-pointer text-sm font-medium transition-colors',
                    isRegistered(opt.value)
                      ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                      : form.day_of_week === opt.value
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                        : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input
                    type="radio"
                    v-model="form.day_of_week"
                    :value="opt.value"
                    :disabled="isRegistered(opt.value)"
                    class="sr-only"
                  />
                  {{ opt.label }}
                  <span v-if="isRegistered(opt.value)" class="text-xs">（登録済）</span>
                </label>
              </div>
            </div>

            <!-- 利用種別 -->
            <div>
              <label :class="labelClass">利用種別 <span class="text-red-500">*</span></label>
              <div class="flex gap-4 mt-1">
                <label
                  v-for="opt in STATUS_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'flex items-center gap-2 px-4 py-2 border rounded cursor-pointer text-sm transition-colors',
                    form.status === opt.value
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.status" :value="opt.value" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
            </div>

            <!-- 期間 -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">開始日 <span class="text-red-500">*</span></label>
                <input v-model="form.start_date" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">終了日 <span class="text-gray-400 text-xs">（未定の場合は空欄）</span></label>
                <input v-model="form.end_date" type="date" :class="inputClass" />
              </div>
            </div>

            <!-- 送迎 -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
              <input v-model="form.pickup_required" type="checkbox" id="pickup" class="w-4 h-4" />
              <label for="pickup" class="text-sm text-gray-700">この曜日は送迎が必要</label>
            </div>

            <!-- メモ -->
            <div>
              <label :class="labelClass">メモ</label>
              <input v-model="form.memo" type="text" :class="inputClass" placeholder="例：学校の終わり次第来所" />
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
