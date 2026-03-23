<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  schools: Array,
})

const form = reactive({
  name: '',
  name_kana: '',
  gender: '',
  birthdate: '',
  grade: '',
  school_id: '',
  disability_type: '',
  disability_note: '',
  allergy_note: '',
  care_note: '',
  pickup_address: '',
  contract_start_date: '',
  contract_status: 'active',
  memo: '',
  schedule_days: [],
})

const DAY_OPTIONS = [
  { value: 'mon', label: '月' },
  { value: 'tue', label: '火' },
  { value: 'wed', label: '水' },
  { value: 'thu', label: '木' },
  { value: 'fri', label: '金' },
  { value: 'sat', label: '土' },
]

const store = () => {
  Inertia.post(route('children.store'), form)
}

// 入力フィールドの共通クラス
const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head title="児童登録" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">児童登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="store" class="space-y-6">

            <!-- 基本情報 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">基本情報</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">児童名 <span class="text-red-500">*</span></label>
                  <input v-model="form.name" type="text" :class="inputClass" placeholder="例：山田 太郎" />
                </div>
                <div>
                  <label :class="labelClass">児童名カナ</label>
                  <input v-model="form.name_kana" type="text" :class="inputClass" placeholder="例：ヤマダ タロウ" />
                </div>
                <div>
                  <label :class="labelClass">性別</label>
                  <div class="flex gap-4 mt-2">
                    <label class="flex items-center gap-1 text-sm">
                      <input type="radio" v-model="form.gender" value="male" /> 男
                    </label>
                    <label class="flex items-center gap-1 text-sm">
                      <input type="radio" v-model="form.gender" value="female" /> 女
                    </label>
                    <label class="flex items-center gap-1 text-sm">
                      <input type="radio" v-model="form.gender" value="other" /> その他
                    </label>
                  </div>
                </div>
                <div>
                  <label :class="labelClass">生年月日</label>
                  <input v-model="form.birthdate" type="date" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">学年</label>
                  <select v-model="form.grade" :class="inputClass">
                    <option value="">選択してください</option>
                    <option v-for="g in ['小1','小2','小3','小4','小5','小6','中1','中2','中3','高1','高2','高3','特支']" :key="g" :value="g">{{ g }}</option>
                  </select>
                </div>
                <div>
                  <label :class="labelClass">学校</label>
                  <select v-model="form.school_id" :class="inputClass">
                    <option value="">選択してください</option>
                    <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                  </select>
                </div>
                <div class="md:col-span-2">
                  <label :class="labelClass">送迎先住所</label>
                  <input v-model="form.pickup_address" type="text" :class="inputClass" placeholder="例：〇〇市△△町1-2-3" />
                </div>
              </div>
            </section>

            <!-- 支援情報 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">支援・配慮情報</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">障がい種別</label>
                  <input v-model="form.disability_type" type="text" :class="inputClass" placeholder="例：自閉スペクトラム症、ADHD" />
                </div>
                <div>
                  <label :class="labelClass">障がい備考</label>
                  <textarea v-model="form.disability_note" :class="inputClass" rows="2" placeholder="特性や注意事項など" />
                </div>
                <div>
                  <label :class="labelClass">アレルギー</label>
                  <textarea v-model="form.allergy_note" :class="inputClass" rows="2" placeholder="食物アレルギー・薬物アレルギーなど" />
                </div>
                <div>
                  <label :class="labelClass">配慮事項</label>
                  <textarea v-model="form.care_note" :class="inputClass" rows="2" placeholder="環境調整・感覚過敏・コミュニケーションなど" />
                </div>
              </div>
            </section>

            <!-- 契約情報 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">契約情報</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">契約開始日</label>
                  <input v-model="form.contract_start_date" type="date" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">契約状況 <span class="text-red-500">*</span></label>
                  <select v-model="form.contract_status" :class="inputClass">
                    <option value="active">契約中</option>
                    <option value="suspended">一時停止</option>
                    <option value="ended">契約終了</option>
                  </select>
                </div>
              </div>
            </section>

            <!-- 利用曜日 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">利用曜日</h3>
              <p class="text-xs text-gray-400 mb-3">登録後に個別の編集・削除もできます</p>
              <div class="flex gap-3 flex-wrap">
                <label
                  v-for="opt in DAY_OPTIONS"
                  :key="opt.value"
                  :class="[
                    'flex items-center justify-center w-14 h-14 border rounded-lg cursor-pointer text-sm font-medium transition-colors',
                    form.schedule_days.includes(opt.value)
                      ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                      : 'border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  <input
                    type="checkbox"
                    :value="opt.value"
                    v-model="form.schedule_days"
                    class="sr-only"
                  />
                  {{ opt.label }}
                </label>
              </div>
            </section>

            <!-- メモ -->
            <section>
              <label :class="labelClass">メモ</label>
              <textarea v-model="form.memo" :class="inputClass" rows="3" placeholder="その他の連絡事項など" />
            </section>

            <!-- ボタン -->
            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
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
