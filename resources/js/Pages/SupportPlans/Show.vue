<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { router } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'

const props = defineProps({
  child:     Object,
  plan:      Object,
  guardians: { type: Array, default: () => [] },
})

// ── 承認フロー ─────────────────────────────
const PLAN_STATUS = {
  draft:     { label: '原案',   cls: 'bg-gray-100 text-gray-600' },
  approved:  { label: '承認済', cls: 'bg-blue-100 text-blue-700' },
  delivered: { label: '交付済', cls: 'bg-green-100 text-green-700' },
}
const stepIndex = computed(() => ({ draft: 0, approved: 1, delivered: 2 }[props.plan.status] ?? 0))

const approve = () => {
  if (!confirm('この計画を児発管として承認しますか？')) return
  router.post(route('children.support-plans.approve', [props.child.id, props.plan.id]))
}

// ── 担当者会議 ─────────────────────────────
const showMeetingForm = ref(false)
const meetingForm = reactive({
  held_at: new Date().toISOString().slice(0, 10),
  attendees: '',
  minutes: '',
})
const storeMeeting = () => {
  router.post(route('children.support-plans.meetings.store', [props.child.id, props.plan.id]), meetingForm, {
    onSuccess: () => {
      showMeetingForm.value = false
      meetingForm.attendees = ''
      meetingForm.minutes = ''
    },
  })
}
const destroyMeeting = (m) => {
  if (!confirm('この会議記録を削除しますか？')) return
  router.delete(route('children.support-plans.meetings.destroy', [props.child.id, props.plan.id, m.id]))
}

// ── 同意・交付 ─────────────────────────────
const showConsentForm = ref(false)
const consentForm = reactive({
  guardian_id:  props.guardians[0]?.id ?? '',
  consented_at: new Date().toISOString().slice(0, 10),
  method:       'paper',
  delivered_at: new Date().toISOString().slice(0, 10),
})
const storeConsent = () => {
  router.post(route('children.support-plans.consents.store', [props.child.id, props.plan.id]), {
    ...consentForm,
    guardian_id:  consentForm.guardian_id || null,
    delivered_at: consentForm.delivered_at || null,
  }, {
    onSuccess: () => { showConsentForm.value = false },
  })
}

const fmtDateTime = (s) => s ? new Date(s).toLocaleString('ja-JP', { dateStyle: 'medium', timeStyle: 'short' }) : '―'

const destroy = () => {
  if (confirm('この個別支援計画を削除しますか？')) {
    router.delete(route('children.support-plans.destroy', [props.child.id, props.plan.id]))
  }
}

const DOMAIN_LABELS = {
  health_life:            '健康・生活',
  motor_sensory:          '運動・感覚',
  cognition_behavior:     '認知・行動',
  language_communication: '言語・コミュニケーション',
  social_relations:       '人間関係・社会性',
}

const timeHM = (t) => t ? t.slice(0, 5) : null
const hasDomains = props.plan.five_domains && Object.values(props.plan.five_domains).some(v => v)
</script>

<template>
  <Head :title="child.name + ' - 個別支援計画'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4 flex-wrap">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">個別支援計画 — {{ plan.plan_date }}</h2>
        <span :class="['text-xs font-medium px-2 py-1 rounded-full', PLAN_STATUS[plan.status]?.cls ?? 'bg-gray-100 text-gray-600']">
          {{ PLAN_STATUS[plan.status]?.label ?? plan.status }}
        </span>
        <span
          :class="[
            'text-xs font-medium px-2 py-1 rounded-full',
            plan.guardian_agreement ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
          ]"
        >
          {{ plan.guardian_agreement ? '同意済' : '同意待ち' }}
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />
        <BreezeValidationErrors />

        <!-- 計画プロセス（原案 → 承認 → 同意・交付） -->
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-1">
              <template v-for="(step, i) in ['原案作成', '児発管承認', '同意・交付']" :key="i">
                <div class="flex items-center gap-1.5">
                  <span :class="[
                    'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold',
                    i <= stepIndex ? 'bg-primary-500 text-white' : 'bg-gray-200 text-gray-400'
                  ]">{{ i < stepIndex ? '✓' : i + 1 }}</span>
                  <span :class="['text-xs font-medium', i <= stepIndex ? 'text-gray-800' : 'text-gray-400']">{{ step }}</span>
                </div>
                <div v-if="i < 2" :class="['w-8 h-0.5 mx-1', i < stepIndex ? 'bg-primary-400' : 'bg-gray-200']" />
              </template>
            </div>
            <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="flex gap-2">
              <button v-if="plan.status === 'draft'" @click="approve"
                class="px-4 py-1.5 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600">
                児発管として承認
              </button>
              <button v-if="plan.status !== 'draft'" @click="showConsentForm = !showConsentForm"
                class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">
                {{ showConsentForm ? '閉じる' : '同意・交付を記録' }}
              </button>
            </div>
          </div>
          <div v-if="plan.approved_at" class="mt-2 text-xs text-gray-500">
            承認：{{ plan.approved_by_staff?.name ?? '―' }}／{{ fmtDateTime(plan.approved_at) }}
          </div>

          <!-- 同意・交付フォーム -->
          <div v-if="showConsentForm" class="mt-4 pt-4 border-t space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">保護者</label>
                <select v-model="consentForm.guardian_id" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
                  <option value="">未指定</option>
                  <option v-for="g in guardians" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">同意日 <span class="text-red-500">*</span></label>
                <input v-model="consentForm.consented_at" type="date" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">方法</label>
                <select v-model="consentForm.method" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
                  <option value="paper">書面（署名・押印）</option>
                  <option value="electronic">電子同意</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">交付日（未交付なら空欄）</label>
                <input v-model="consentForm.delivered_at" type="date" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm" />
              </div>
            </div>
            <div class="flex justify-end">
              <button @click="storeConsent" class="px-5 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">記録する</button>
            </div>
          </div>

          <!-- 同意・交付の履歴 -->
          <div v-if="plan.consents?.length" class="mt-4 pt-3 border-t">
            <div class="text-xs font-semibold text-gray-500 mb-2">同意・交付の記録</div>
            <ul class="space-y-1">
              <li v-for="c in plan.consents" :key="c.id" class="text-xs text-gray-600 flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-800">{{ c.guardian?.name ?? '保護者' }}</span>
                <span>同意 {{ fmtDateTime(c.consented_at) }}</span>
                <span class="px-1.5 py-0.5 rounded-md bg-gray-100">{{ c.method === 'electronic' ? '電子' : '書面' }}</span>
                <span v-if="c.delivered_at" class="px-1.5 py-0.5 rounded-md bg-green-50 text-green-700">交付 {{ fmtDateTime(c.delivered_at) }}</span>
                <span v-else class="px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700">未交付</span>
              </li>
            </ul>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <a
            :href="route('children.support-plans.pdf', [child.id, plan.id])"
            class="px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700"
          >PDF出力</a>
          <template v-if="['admin','leader'].includes($page.props.auth.staff_role)">
            <Link
              :href="route('children.support-plans.edit', [child.id, plan.id])"
              class="px-4 py-2 text-sm bg-primary-500 text-white rounded-md hover:bg-primary-600"
            >編集</Link>
            <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded-md hover:bg-red-50">削除</button>
          </template>
        </div>

        <!-- メタ情報 -->
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500">作成日</dt>
              <dd class="font-medium">{{ plan.plan_date }}</dd>
            </div>
            <div v-if="plan.valid_from || plan.valid_to">
              <dt class="text-xs text-gray-500">有効期間</dt>
              <dd>{{ plan.valid_from }} 〜 {{ plan.valid_to }}</dd>
            </div>
            <div v-if="plan.guardian_agreement_date">
              <dt class="text-xs text-gray-500">同意取得日</dt>
              <dd>{{ plan.guardian_agreement_date }}</dd>
            </div>
            <div v-if="plan.staff">
              <dt class="text-xs text-gray-500">担当者</dt>
              <dd>{{ plan.staff?.name }}</dd>
            </div>
            <div v-if="plan.previous_plan">
              <dt class="text-xs text-gray-500">前回計画</dt>
              <dd>
                <Link :href="route('children.support-plans.show', [child.id, plan.previous_plan.id])" class="text-primary-600 hover:underline">
                  {{ plan.previous_plan.plan_date }}
                </Link>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">計画支援時間（時間区分算定の根拠）</dt>
              <dd v-if="plan.planned_duration_minutes">
                <span v-if="timeHM(plan.planned_start_time)">{{ timeHM(plan.planned_start_time) }} 〜 {{ timeHM(plan.planned_end_time) }}／</span>
                {{ plan.planned_duration_minutes }}分
              </dd>
              <dd v-else class="text-red-600 text-xs font-medium">未設定 — 請求計算で時間区分を判定できません</dd>
            </div>
          </dl>
        </div>

        <!-- 目標・方針 -->
        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
          <div v-if="plan.long_term_goal">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">長期目標</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ plan.long_term_goal }}</p>
          </div>
          <div v-if="plan.short_term_goal">
            <h3 class="text-xs font-semibold text-primary-600 uppercase tracking-wide mb-1">短期目標</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-primary-50 p-3 rounded-md">{{ plan.short_term_goal }}</p>
          </div>
          <div v-if="plan.support_policy">
            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">支援方針</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-blue-50 p-3 rounded-md">{{ plan.support_policy }}</p>
          </div>
          <div v-if="plan.program_content">
            <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-1">支援内容・プログラム</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-green-50 p-3 rounded-md">{{ plan.program_content }}</p>
          </div>
        </div>

        <!-- 担当者会議の記録 -->
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">担当者会議の記録</h3>
            <button v-if="['admin','leader'].includes($page.props.auth.staff_role)"
              @click="showMeetingForm = !showMeetingForm"
              class="text-xs px-3 py-1 bg-primary-500 text-white rounded-md hover:bg-primary-600">
              {{ showMeetingForm ? '閉じる' : '＋ 会議を記録' }}
            </button>
          </div>

          <div v-if="showMeetingForm" class="mb-4 p-4 bg-primary-50/40 rounded-md space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">開催日 <span class="text-red-500">*</span></label>
                <input v-model="meetingForm.held_at" type="date" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">出席者（読点・カンマ区切り）</label>
                <input v-model="meetingForm.attendees" type="text" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm"
                  placeholder="例：山田（児発管）、佐藤（保育士）" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">議事概要・専門的見地からの意見</label>
              <textarea v-model="meetingForm.minutes" rows="3" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm" />
            </div>
            <div class="flex justify-end">
              <button @click="storeMeeting" class="px-5 py-2 text-sm bg-primary-500 text-white rounded-md hover:bg-primary-600">登録</button>
            </div>
          </div>

          <div v-if="!plan.meetings?.length && !showMeetingForm" class="text-sm text-gray-400">
            会議記録がありません。計画作成にあたる担当者会議の記録は指定基準で求められます。
          </div>
          <ul v-else class="space-y-2">
            <li v-for="m in plan.meetings" :key="m.id" class="p-3 bg-gray-50 rounded-md text-sm">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-800">{{ m.held_at?.slice(0, 10) }}</span>
                <span v-if="m.attendees?.length" class="text-xs text-gray-500">出席：{{ m.attendees.join('、') }}</span>
                <button v-if="['admin','leader'].includes($page.props.auth.staff_role)"
                  @click="destroyMeeting(m)"
                  class="ml-auto text-xs text-gray-400 hover:text-red-500">削除</button>
              </div>
              <p v-if="m.minutes" class="text-xs text-gray-600 mt-1 whitespace-pre-wrap">{{ m.minutes }}</p>
            </li>
          </ul>
        </div>

        <!-- 5領域との関連 -->
        <div v-if="hasDomains" class="bg-white border border-gray-200 rounded-lg p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">5領域との関連</h3>
          <dl class="space-y-3">
            <template v-for="(label, key) in DOMAIN_LABELS" :key="key">
              <div v-if="plan.five_domains?.[key]">
                <dt class="text-xs font-medium text-gray-600 mb-1">{{ label }}</dt>
                <dd class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-2 rounded-md">{{ plan.five_domains[key] }}</dd>
              </div>
            </template>
          </dl>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
