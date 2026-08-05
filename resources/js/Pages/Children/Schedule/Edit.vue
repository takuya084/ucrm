<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  child:    Object,
  schedule: Object,
})

const DAY_LABELS = { mon: '月', tue: '火', wed: '水', thu: '木', fri: '金', sat: '土' }

const STATUS_OPTIONS = [
  { value: 'regular',   label: '定期利用' },
  { value: 'temporary', label: '一時利用' },
  { value: 'trial',     label: '体験利用' },
]

const form = reactive({
  day_of_week:     props.schedule.day_of_week,
  start_date:      props.schedule.start_date ?? '',
  end_date:        props.schedule.end_date ?? '',
  status:          props.schedule.status ?? 'regular',
  pickup_required: props.schedule.pickup_required ?? false,
  memo:            props.schedule.memo ?? '',
})

const update = () => {
  router.patch(
    route('children.schedules.update', { child: props.child.id, schedule: props.schedule.id }),
    form
  )
}

const destroy = () => {
  if (confirm('この曜日のスケジュールを削除しますか？')) {
    router.delete(
      route('children.schedules.destroy', { child: props.child.id, schedule: props.schedule.id })
    )
  }
}

const inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - 利用曜日編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">
          ← {{ child.name }} へ戻る
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">
          利用曜日 編集 ―
          <span class="text-primary-600">{{ DAY_LABELS[schedule.day_of_week] }}曜日</span>
        </h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-5">

            <!-- 曜日（変更不可・表示のみ） -->
            <div class="p-3 bg-primary-50 border border-primary-200 rounded-md text-sm text-primary-700 font-medium">
              対象曜日：{{ DAY_LABELS[schedule.day_of_week] }}曜日
              <span class="text-xs text-primary-400 ml-2">（曜日は変更できません）</span>
            </div>

            <!-- 利用種別 -->
            <div>
              <label :class="labelClass">利用種別 <span class="text-red-500">*</span></label>
              <div class="flex gap-4 mt-1">
                <label
                  v-for="opt in STATUS_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'flex items-center gap-2 px-4 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                    form.status === opt.value
                      ? 'border-primary-500 bg-primary-50 text-primary-700 font-medium'
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
                <label :class="labelClass">終了日</label>
                <input v-model="form.end_date" type="date" :class="inputClass" />
                <p class="text-xs text-gray-400 mt-1">終了日を過ぎると利用曜日から非表示になります（履歴は残ります）</p>
              </div>
            </div>

            <!-- 送迎 -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-md">
              <input v-model="form.pickup_required" type="checkbox" id="pickup" class="w-4 h-4" />
              <label for="pickup" class="text-sm text-gray-700">この曜日は送迎が必要</label>
            </div>

            <!-- メモ -->
            <div>
              <label :class="labelClass">メモ</label>
              <input v-model="form.memo" type="text" :class="inputClass" />
            </div>

            <div class="flex justify-between pt-4 border-t">
              <button type="button" @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-500 rounded-md hover:bg-red-50">
                削除
              </button>
              <div class="flex gap-3">
                <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                  キャンセル
                </Link>
                <button type="submit" class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600">
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
