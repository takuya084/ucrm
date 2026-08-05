<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  child:        Object,
  previousPlan: Object,
})

const today = new Date().toISOString().slice(0, 10)

// R6基準の5領域（支援計画は各領域との関連を明示する）
const FIVE_DOMAINS = [
  { key: 'health_life',            label: '健康・生活' },
  { key: 'motor_sensory',          label: '運動・感覚' },
  { key: 'cognition_behavior',     label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations',       label: '人間関係・社会性' },
]

const timeHM = (t) => t ? t.slice(0, 5) : ''

const form = reactive({
  previous_plan_id:        props.previousPlan?.id ?? null,
  plan_date:               today,
  valid_from:              props.previousPlan?.valid_to ?? today,
  valid_to:                '',
  long_term_goal:          props.previousPlan?.long_term_goal ?? '',
  short_term_goal:         '',
  support_policy:          props.previousPlan?.support_policy ?? '',
  program_content:         '',
  planned_start_time:      timeHM(props.previousPlan?.planned_start_time),
  planned_end_time:        timeHM(props.previousPlan?.planned_end_time),
  planned_duration_minutes: props.previousPlan?.planned_duration_minutes ?? null,
  five_domains:            Object.fromEntries(
    FIVE_DOMAINS.map(d => [d.key, props.previousPlan?.five_domains?.[d.key] ?? ''])
  ),
  guardian_agreement:      false,
  guardian_agreement_date: '',
})

// 開始・終了時刻から支援時間（分）を自動計算（手入力での上書きも可）
const syncDuration = () => {
  if (!form.planned_start_time || !form.planned_end_time) return
  const [sh, sm] = form.planned_start_time.split(':').map(Number)
  const [eh, em] = form.planned_end_time.split(':').map(Number)
  const diff = (eh * 60 + em) - (sh * 60 + sm)
  if (diff > 0) form.planned_duration_minutes = diff
}

const store = () => {
  const domains = Object.fromEntries(
    Object.entries(form.five_domains).filter(([, v]) => v && v.trim() !== '')
  )
  router.post(route('children.support-plans.store', props.child.id), {
    ...form,
    planned_start_time:       form.planned_start_time || null,
    planned_end_time:         form.planned_end_time || null,
    planned_duration_minutes: form.planned_duration_minutes || null,
    five_domains:             Object.keys(domains).length ? domains : null,
  })
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

const inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - 個別支援計画'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">個別支援計画 作成 — {{ child.name }}</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <!-- AI下書き生成 -->
          <div class="mb-5 flex items-center gap-3 flex-wrap">
            <button
              type="button"
              @click="generateDraft"
              :disabled="aiLoading"
              class="flex items-center gap-2 px-4 py-2 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-700 disabled:opacity-50"
            >
              <span v-if="aiLoading" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ aiLoading ? 'AI生成中...' : 'AIで下書き生成' }}
            </button>
            <span v-if="aiError" class="text-sm text-red-600">{{ aiError }}</span>
          </div>

          <!-- 前回計画からのコピー通知 -->
          <div v-if="previousPlan" class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded-md text-sm">
            <span class="font-medium text-blue-700">前回計画（{{ previousPlan.valid_from?.slice(0, 10) }} 〜 {{ previousPlan.valid_to?.slice(0, 10) }}）から一部引き継ぎました。</span>
          </div>

          <form @submit.prevent="store" class="space-y-6">

            <!-- 計画期間 -->
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

            <!-- 目標 -->
            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">目標・方針</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">長期目標</label>
                  <textarea v-model="form.long_term_goal" :class="inputClass" rows="2"
                    placeholder="1年程度の長期的な目標" />
                </div>
                <div>
                  <label :class="labelClass">短期目標</label>
                  <textarea v-model="form.short_term_goal" :class="inputClass" rows="2"
                    placeholder="半年程度の短期的な目標・ステップ" />
                </div>
                <div>
                  <label :class="labelClass">支援方針</label>
                  <textarea v-model="form.support_policy" :class="inputClass" rows="3"
                    placeholder="支援の基本方針、アプローチなど" />
                </div>
              </div>
            </section>

            <!-- 支援内容 -->
            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">支援内容</h3>
              <textarea v-model="form.program_content" :class="inputClass" rows="4"
                placeholder="実施する支援プログラム、活動内容など" />
            </section>

            <!-- 計画支援時間 -->
            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-1 pb-1 border-b">計画支援時間</h3>
              <p class="text-xs text-gray-500 mb-3">
                基本報酬の時間区分は、計画に定めた支援時間で算定されます（令和6年度報酬改定）。未入力の場合、請求計算で時間区分を判定できません。
              </p>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label :class="labelClass">開始時刻</label>
                  <input v-model="form.planned_start_time" type="time" :class="inputClass" @change="syncDuration" />
                </div>
                <div>
                  <label :class="labelClass">終了時刻</label>
                  <input v-model="form.planned_end_time" type="time" :class="inputClass" @change="syncDuration" />
                </div>
                <div>
                  <label :class="labelClass">支援時間（分）</label>
                  <input v-model.number="form.planned_duration_minutes" type="number" min="0" max="720" :class="inputClass" placeholder="例：180" />
                </div>
              </div>
            </section>

            <!-- 5領域との関連 -->
            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-1 pb-1 border-b">5領域との関連</h3>
              <p class="text-xs text-gray-500 mb-3">
                各領域に関連する支援内容を記載します（関連しない領域は空欄で構いません）。
              </p>
              <div class="space-y-3">
                <div v-for="d in FIVE_DOMAINS" :key="d.key">
                  <label :class="labelClass">{{ d.label }}</label>
                  <textarea v-model="form.five_domains[d.key]" :class="inputClass" rows="2"
                    :placeholder="`「${d.label}」に関連する支援内容`" />
                </div>
              </div>
            </section>

            <!-- 保護者同意 -->
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
                    class="border border-gray-300 rounded-md px-2 py-1 text-sm" />
                </div>
              </div>
            </section>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600">
                計画を保存
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
