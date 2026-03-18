<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  school: Object,
  schoolTypeLabels: Object,
})

const form = reactive({
  name:             props.school.name ?? '',
  name_kana:        props.school.name_kana ?? '',
  address:          props.school.address ?? '',
  school_type:      props.school.school_type ?? 'elementary',
  end_time_regular: props.school.end_time_regular ?? '',
  end_time_early:   props.school.end_time_early ?? '',
  memo:             props.school.memo ?? '',
})

const update = () => {
  Inertia.patch(route('schools.update', props.school.id), form)
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="school.name + ' - 編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('schools.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">学校編集</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-5">

            <!-- 学校種別 -->
            <div>
              <label :class="labelClass">学校種別 <span class="text-red-500">*</span></label>
              <div class="flex flex-wrap gap-2 mt-1">
                <label
                  v-for="(label, value) in schoolTypeLabels"
                  :key="value"
                  :class="[
                    'px-3 py-2 border rounded cursor-pointer text-sm transition-colors',
                    form.school_type === value
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.school_type" :value="value" class="sr-only" />
                  {{ label }}
                </label>
              </div>
            </div>

            <!-- 学校名 -->
            <div>
              <label :class="labelClass">学校名 <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" :class="inputClass" />
            </div>

            <!-- 学校名（かな） -->
            <div>
              <label :class="labelClass">学校名（かな）</label>
              <input v-model="form.name_kana" type="text" :class="inputClass" />
            </div>

            <!-- 住所 -->
            <div>
              <label :class="labelClass">住所</label>
              <input v-model="form.address" type="text" :class="inputClass" />
            </div>

            <!-- 下校時刻 -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">通常下校時刻</label>
                <input v-model="form.end_time_regular" type="time" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">早退・短縮時下校時刻</label>
                <input v-model="form.end_time_early" type="time" :class="inputClass" />
              </div>
            </div>

            <!-- メモ -->
            <div>
              <label :class="labelClass">メモ</label>
              <input v-model="form.memo" type="text" :class="inputClass" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('schools.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                更新する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
