<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  staff: Object,
  roleLabels: Object,
  qualifications: Array,
  qualificationTypes: Object,
})

const form = reactive({
  name:            props.staff.name ?? '',
  role:            props.staff.role ?? 'staff',
  employment_type: props.staff.employment_type ?? 'full_time',
  monthly_salary:  props.staff.monthly_salary ?? null,
  hourly_wage:     props.staff.hourly_wage ?? null,
  is_active:       props.staff.is_active ?? true,
  qualifications:  [...(props.qualifications ?? [])],
})

const EMPLOYMENT_TYPE_LABELS = {
  full_time: '常勤',
  part_time: 'パート',
  contract:  '契約',
}

// 雇用形態切替時に不要な給与欄をクリア（人件費誤計算を防止）
watch(() => form.employment_type, (t) => {
  if (t === 'full_time') form.hourly_wage = null
  else if (t === 'part_time') form.monthly_salary = null
})

const update = () => {
  router.patch(route('staff.update', props.staff.id), form)
}

const inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="staff.name + ' - 編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('staff.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">職員編集</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-5">

            <!-- メールアドレス（読み取り専用） -->
            <div>
              <label :class="labelClass">メールアドレス</label>
              <input :value="staff.user?.email" type="email" disabled
                class="w-full border border-gray-100 bg-gray-50 rounded-md px-3 py-2 text-sm text-gray-500" />
            </div>

            <!-- 氏名 -->
            <div>
              <label :class="labelClass">氏名 <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" :class="inputClass" />
            </div>

            <!-- 役割 -->
            <div>
              <label :class="labelClass">役割 <span class="text-red-500">*</span></label>
              <div class="flex flex-wrap gap-2 mt-1">
                <label
                  v-for="(label, value) in roleLabels"
                  :key="value"
                  :class="[
                    'px-3 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                    form.role === value
                      ? 'border-primary-500 bg-primary-50 text-primary-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="radio" v-model="form.role" :value="value" class="sr-only" />
                  {{ label }}
                </label>
              </div>
            </div>

            <!-- 雇用形態 -->
            <div>
              <label :class="labelClass">雇用形態</label>
              <div class="flex gap-2 mt-1">
                <label
                  v-for="(label, value) in EMPLOYMENT_TYPE_LABELS" :key="value"
                  :class="['px-3 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                    form.employment_type === value
                      ? 'border-primary-500 bg-primary-50 text-primary-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50']">
                  <input type="radio" v-model="form.employment_type" :value="value" class="sr-only" />
                  {{ label }}
                </label>
              </div>
            </div>

            <!-- 給与（雇用形態に応じて表示） -->
            <div v-if="form.employment_type === 'full_time'">
              <label :class="labelClass">月給</label>
              <input v-model.number="form.monthly_salary" type="number" min="0" step="1000"
                placeholder="例: 280000" :class="inputClass" />
              <p class="mt-1 text-xs text-gray-400">常勤のため月給のみ入力します。</p>
            </div>
            <div v-else-if="form.employment_type === 'part_time'">
              <label :class="labelClass">時給</label>
              <input v-model.number="form.hourly_wage" type="number" min="0" step="10"
                placeholder="例: 1200" :class="inputClass" />
              <p class="mt-1 text-xs text-gray-400">パートのため時給のみ入力します。シフトラベルの勤務時間に連動して人件費を計算します。</p>
            </div>
            <div v-else-if="form.employment_type === 'contract'" class="grid grid-cols-2 gap-3">
              <div>
                <label :class="labelClass">月給</label>
                <input v-model.number="form.monthly_salary" type="number" min="0" step="1000"
                  placeholder="例: 280000" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">時給</label>
                <input v-model.number="form.hourly_wage" type="number" min="0" step="10"
                  placeholder="例: 1200" :class="inputClass" />
              </div>
              <p class="col-span-2 text-xs text-gray-400">契約形態に応じて使用する項目のみ入力してください。</p>
            </div>

            <!-- ステータス -->
            <div>
              <label :class="labelClass">ステータス</label>
              <div class="flex gap-4 mt-1">
                <label :class="[
                  'px-3 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                  form.is_active
                    ? 'border-green-500 bg-green-50 text-green-700 font-medium'
                    : 'border-gray-300 hover:bg-gray-50'
                ]">
                  <input type="radio" v-model="form.is_active" :value="true" class="sr-only" />
                  有効
                </label>
                <label :class="[
                  'px-3 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                  !form.is_active
                    ? 'border-red-500 bg-red-50 text-red-700 font-medium'
                    : 'border-gray-300 hover:bg-gray-50'
                ]">
                  <input type="radio" v-model="form.is_active" :value="false" class="sr-only" />
                  無効
                </label>
              </div>
            </div>

            <!-- 保有資格 -->
            <div>
              <label :class="labelClass">保有資格</label>
              <div class="flex flex-wrap gap-2 mt-1">
                <label
                  v-for="(info, code) in qualificationTypes"
                  :key="code"
                  :class="[
                    'px-3 py-2 border rounded-md cursor-pointer text-sm transition-colors',
                    form.qualifications.includes(code)
                      ? 'border-primary-500 bg-primary-50 text-primary-700 font-medium'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input type="checkbox" :value="code"
                    :checked="form.qualifications.includes(code)"
                    @change="e => {
                      if (e.target.checked) {
                        form.qualifications.push(code)
                      } else {
                        form.qualifications = form.qualifications.filter(q => q !== code)
                      }
                    }"
                    class="sr-only" />
                  {{ info.name }}
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('staff.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600">
                更新する
              </button>
            </div>
          </form>
        </div>

        <!-- 勤務パターン設定リンク -->
        <div v-if="$page.props.auth.staff_role === 'admin'" class="mt-4 bg-white border border-gray-200 sm:rounded-lg p-6">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-700">勤務パターン設定</h3>
              <p class="text-xs text-gray-500 mt-1">曜日ごとの基本勤務パターンを設定します。シフト自動生成に使用されます。</p>
            </div>
            <Link :href="route('staff.work-patterns.edit', staff.id)"
              class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
              設定する →
            </Link>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
