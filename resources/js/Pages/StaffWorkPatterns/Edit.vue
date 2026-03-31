<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  staff:     Object,   // { id, name }
  patterns:  Array,    // [{ day_of_week, start_time, work_type }] x 7
  dayLabels: Object,   // { mon: '月', ... }
  labels:    Array,    // [{ name, is_off }]
})

const form = reactive({
  patterns: props.patterns.map(p => ({ ...p })),
})

const isOff = (type) => {
  if (!type) return false
  const label = props.labels.find(l => l.name === type)
  return label ? label.is_off : false
}

// 15分刻みの時刻選択肢を生成 (06:00〜22:00)
const timeOptions = []
for (let h = 6; h <= 22; h++) {
  for (let m = 0; m < 60; m += 15) {
    timeOptions.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'))
  }
}

const save = () => {
  Inertia.patch(route('staff.work-patterns.update', props.staff.id), form)
}
</script>

<template>
  <Head :title="`勤務パターン設定 - ${staff.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">勤務パターン設定 — {{ staff.name }}</h2>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="save" class="space-y-2">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b text-left text-gray-500">
                  <th class="py-2 px-2 w-12">曜日</th>
                  <th class="py-2 px-2">勤務区分</th>
                  <th class="py-2 px-2 w-32">開始時刻</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(p, i) in form.patterns" :key="p.day_of_week"
                  class="border-b"
                  :class="{
                    'bg-blue-50': p.day_of_week === 'sat',
                    'bg-red-50': p.day_of_week === 'sun',
                  }">
                  <td class="py-2 px-2 font-medium"
                    :class="{
                      'text-blue-500': p.day_of_week === 'sat',
                      'text-red-500': p.day_of_week === 'sun',
                    }">
                    {{ dayLabels[p.day_of_week] }}
                  </td>
                  <td class="py-2 px-2">
                    <select v-model="p.work_type"
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                      <option value="">―</option>
                      <option v-for="l in labels" :key="l.name" :value="l.name">{{ l.name }}</option>
                    </select>
                  </td>
                  <td class="py-2 px-2">
                    <select
                      v-if="!isOff(p.work_type)"
                      v-model="p.start_time"
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    >
                      <option :value="null">--:--</option>
                      <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <span v-else class="text-gray-400 text-sm">—</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="flex justify-between items-center pt-4 border-t">
              <Link :href="route('staff.index')" class="text-sm text-gray-500 hover:underline">
                ← 職員一覧に戻る
              </Link>
              <button type="submit"
                class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                保存する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
