<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child: Object,
  plan:  Object,
})

const form = reactive({
  previous_plan_id:        props.plan.previous_plan_id ?? null,
  plan_date:               props.plan.plan_date ?? '',
  valid_from:              props.plan.valid_from ?? '',
  valid_to:                props.plan.valid_to ?? '',
  long_term_goal:          props.plan.long_term_goal ?? '',
  short_term_goal:         props.plan.short_term_goal ?? '',
  support_policy:          props.plan.support_policy ?? '',
  program_content:         props.plan.program_content ?? '',
  guardian_agreement:      props.plan.guardian_agreement ?? false,
  guardian_agreement_date: props.plan.guardian_agreement_date ?? '',
})

const update = () => {
  Inertia.patch(route('children.support-plans.update', [props.child.id, props.plan.id]), form)
}

const aiLoading = ref(false)
const aiError   = ref('')

const generateDraft = async () => {
  aiLoading.value = true
  aiError.value   = ''
  try {
    const res = await fetch(route('ai-draft.support-plan', props.child.id), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    })
    const data = await res.json()
    if (!res.ok) {
      aiError.value = data.error ?? 'AI生成に失敗しました'
      return
    }
    if (data.long_term_goal)  form.long_term_goal  = data.long_term_goal
    if (data.short_term_goal) form.short_term_goal = data.short_term_goal
    if (data.support_policy)  form.support_policy  = data.support_policy
    if (data.program_content) form.program_content = data.program_content
  } catch {
    aiError.value = '通信エラーが発生しました'
  } finally {
    aiLoading.value = false
  }
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - 個別支援計画編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.support-plans.show', [child.id, plan.id])" class="text-gray-400 hover:text-gray-600 text-sm">← 詳細へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">個別支援計画 編集 — {{ plan.plan_date }}</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <!-- AI下書き生成 -->
          <div class="mb-5 flex items-center gap-3 flex-wrap">
            <button
              type="button"
              @click="generateDraft"
              :disabled="aiLoading"
              class="flex items-center gap-2 px-4 py-2 text-sm bg-purple-600 text-white rounded hover:bg-purple-700 disabled:opacity-50"
            >
              <span v-if="aiLoading" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span v-else>✦</span>
              {{ aiLoading ? 'AI生成中...' : 'AIで下書き生成' }}
            </button>
            <span v-if="aiError" class="text-sm text-red-600">{{ aiError }}</span>
          </div>

          <form @submit.prevent="update" class="space-y-6">

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">計画情報</h3>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label :class="labelClass">作成日 <span class="text-red-500">*</span></label>
                  <input v-model="form.plan_date" type="date" :class="inputClass" required />
                </div>
                <div>
                  <label :class="labelClass">有効期間（開始）</label>
                  <input v-model="form.valid_from" type="date" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">有効期間（終了）</label>
                  <input v-model="form.valid_to" type="date" :class="inputClass" />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">目標・方針</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">長期目標</label>
                  <textarea v-model="form.long_term_goal" :class="inputClass" rows="2" />
                </div>
                <div>
                  <label :class="labelClass">短期目標</label>
                  <textarea v-model="form.short_term_goal" :class="inputClass" rows="2" />
                </div>
                <div>
                  <label :class="labelClass">支援方針</label>
                  <textarea v-model="form.support_policy" :class="inputClass" rows="3" />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">支援内容</h3>
              <textarea v-model="form.program_content" :class="inputClass" rows="4" />
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">保護者同意</h3>
              <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                  <input v-model="form.guardian_agreement" type="checkbox" class="w-4 h-4" />
                  保護者の同意を得た
                </label>
                <div v-if="form.guardian_agreement" class="flex items-center gap-2">
                  <label class="text-sm text-gray-600">同意取得日：</label>
                  <input v-model="form.guardian_agreement_date" type="date"
                    class="border border-gray-300 rounded px-2 py-1 text-sm" />
                </div>
              </div>
            </section>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.support-plans.show', [child.id, plan.id])" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
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
