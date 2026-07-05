<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { ref, reactive, computed } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  committees:      Array,
  restraints:      Array,
  bcps:            Object,   // keyBy('type') → { infection: {...}, disaster: {...} }
  safetyPlans:     Array,
  selfEvaluations: Array,
  children:        Array,
})

const activeTab = ref('committee')

// 現在年度（4月始まり）
const now = new Date()
const currentFiscalYear = String(now.getMonth() + 1 >= 4 ? now.getFullYear() : now.getFullYear() - 1)

const fmtDateTime = (s) => s ? new Date(s).toLocaleString('ja-JP', { dateStyle: 'medium', timeStyle: 'short' }) : '―'
const fmtDate = (s) => s ? s.slice(0, 10) : '―'

// ── 委員会・研修 ─────────────────────────
const COMMITTEE_TYPE = { abuse_prevention: '虐待防止', restraint: '身体拘束適正化' }
const COMMITTEE_CATEGORY = { committee: '委員会', training: '研修' }

const committeeForm = reactive({
  type: 'abuse_prevention', category: 'committee',
  held_at: new Date().toISOString().slice(0, 10),
  attendees: '', minutes: '',
})
const showCommitteeForm = ref(false)

const storeCommittee = () => {
  Inertia.post(route('operation-records.committees.store'), committeeForm, {
    onSuccess: () => {
      showCommitteeForm.value = false
      committeeForm.attendees = ''
      committeeForm.minutes = ''
    },
  })
}

// 年度内の実施状況サマリ（減算チェック用）
const fyStart = `${currentFiscalYear}-04-01`
const committeeStatus = computed(() => {
  const held = { abuse_prevention: { committee: 0, training: 0 }, restraint: { committee: 0, training: 0 } }
  for (const c of props.committees) {
    if (c.held_at >= fyStart) held[c.type][c.category]++
  }
  return held
})

// ── 身体拘束 ─────────────────────────────
const restraintForm = reactive({
  child_id: '', occurred_at: '', duration_minutes: null,
  method: '', reason: '', guardian_notified_at: '',
})
const showRestraintForm = ref(false)

const storeRestraint = () => {
  Inertia.post(route('operation-records.restraints.store'), {
    ...restraintForm,
    guardian_notified_at: restraintForm.guardian_notified_at || null,
    duration_minutes: restraintForm.duration_minutes || null,
  }, {
    onSuccess: () => {
      showRestraintForm.value = false
      Object.assign(restraintForm, { child_id: '', occurred_at: '', duration_minutes: null, method: '', reason: '', guardian_notified_at: '' })
    },
  })
}

const markNotified = (r) => {
  if (!confirm(`${r.child?.name} の身体拘束について、保護者へ報告済みとして記録しますか？`)) return
  Inertia.patch(route('operation-records.restraints.notified', r.id))
}

// ── BCP ─────────────────────────────────
const BCP_TYPE = { infection: '感染症BCP', disaster: '災害BCP' }
const bcpForms = reactive({
  infection: {
    type: 'infection',
    established_at:   props.bcps?.infection?.established_at?.slice(0, 10) ?? '',
    last_reviewed_at: props.bcps?.infection?.last_reviewed_at?.slice(0, 10) ?? '',
    last_training_at: props.bcps?.infection?.last_training_at?.slice(0, 10) ?? '',
  },
  disaster: {
    type: 'disaster',
    established_at:   props.bcps?.disaster?.established_at?.slice(0, 10) ?? '',
    last_reviewed_at: props.bcps?.disaster?.last_reviewed_at?.slice(0, 10) ?? '',
    last_training_at: props.bcps?.disaster?.last_training_at?.slice(0, 10) ?? '',
  },
})
const saveBcp = (type) => Inertia.post(route('operation-records.bcp.upsert'), bcpForms[type])

// ── 安全計画 / 自己評価 ───────────────────
const safetyForm = reactive({
  fiscal_year: currentFiscalYear,
  established_at: '', last_reviewed_at: '',
})
const currentSafety = props.safetyPlans.find(p => p.fiscal_year === currentFiscalYear)
if (currentSafety) {
  safetyForm.established_at   = currentSafety.established_at?.slice(0, 10) ?? ''
  safetyForm.last_reviewed_at = currentSafety.last_reviewed_at?.slice(0, 10) ?? ''
}
const saveSafety = () => Inertia.post(route('operation-records.safety-plan.upsert'), safetyForm)

const selfEvalForm = reactive({
  fiscal_year: currentFiscalYear,
  guardian_survey_at: '', published_at: '', published_url: '',
})
const currentEval = props.selfEvaluations.find(e => e.fiscal_year === currentFiscalYear)
if (currentEval) {
  selfEvalForm.guardian_survey_at = currentEval.guardian_survey_at?.slice(0, 10) ?? ''
  selfEvalForm.published_at       = currentEval.published_at?.slice(0, 10) ?? ''
  selfEvalForm.published_url      = currentEval.published_url ?? ''
}
const saveSelfEval = () => Inertia.post(route('operation-records.self-evaluation.upsert'), selfEvalForm)

const TABS = [
  { key: 'committee', label: '委員会・研修' },
  { key: 'restraint', label: '身体拘束記録' },
  { key: 'bcp',       label: 'BCP・安全計画' },
  { key: 'selfeval',  label: '自己評価公表' },
]

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-xs font-medium text-gray-600 mb-1'
</script>

<template>
  <Head title="運営記録" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800">運営記録</h2>
        <p class="text-xs text-gray-500 mt-1">委員会・研修／身体拘束／BCP・安全計画／自己評価公表 — 未実施・未策定・未公表は減算対象です</p>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />
        <BreezeValidationErrors />

        <!-- タブ -->
        <div class="bg-white shadow-sm rounded-lg px-2 flex gap-1 overflow-x-auto">
          <button
            v-for="tab in TABS" :key="tab.key" type="button"
            @click="activeTab = tab.key"
            :class="[
              'px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
              activeTab === tab.key
                ? 'border-indigo-500 text-indigo-700'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >{{ tab.label }}</button>
        </div>

        <!-- ═══ 委員会・研修 ═══ -->
        <template v-if="activeTab === 'committee'">
          <!-- 年度サマリ -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <template v-for="(tLabel, tKey) in COMMITTEE_TYPE" :key="tKey">
              <div v-for="(cLabel, cKey) in COMMITTEE_CATEGORY" :key="cKey"
                :class="['rounded-lg shadow-sm p-4 text-center', committeeStatus[tKey][cKey] > 0 ? 'bg-emerald-50' : 'bg-red-50']">
                <div :class="['text-2xl font-bold', committeeStatus[tKey][cKey] > 0 ? 'text-emerald-700' : 'text-red-600']">
                  {{ committeeStatus[tKey][cKey] }}回
                </div>
                <div class="text-xs text-gray-500 mt-1">{{ tLabel }}{{ cLabel }}（{{ currentFiscalYear }}年度）</div>
              </div>
            </template>
          </div>

          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700">実施記録</h3>
              <button @click="showCommitteeForm = !showCommitteeForm"
                class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600">
                {{ showCommitteeForm ? '閉じる' : '＋ 記録を追加' }}
              </button>
            </div>

            <div v-if="showCommitteeForm" class="p-5 border-b bg-indigo-50/40 space-y-3">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div>
                  <label :class="labelClass">種別</label>
                  <select v-model="committeeForm.type" :class="inputClass">
                    <option value="abuse_prevention">虐待防止</option>
                    <option value="restraint">身体拘束適正化</option>
                  </select>
                </div>
                <div>
                  <label :class="labelClass">区分</label>
                  <select v-model="committeeForm.category" :class="inputClass">
                    <option value="committee">委員会</option>
                    <option value="training">研修</option>
                  </select>
                </div>
                <div>
                  <label :class="labelClass">実施日</label>
                  <input v-model="committeeForm.held_at" type="date" :class="inputClass" />
                </div>
              </div>
              <div>
                <label :class="labelClass">出席者（読点・カンマ区切り）</label>
                <input v-model="committeeForm.attendees" type="text" :class="inputClass" placeholder="例：山田、佐藤、鈴木" />
              </div>
              <div>
                <label :class="labelClass">議事・研修内容</label>
                <textarea v-model="committeeForm.minutes" rows="3" :class="inputClass" />
              </div>
              <div class="flex justify-end">
                <button @click="storeCommittee" class="px-5 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">登録</button>
              </div>
            </div>

            <div v-if="committees.length === 0" class="py-10 text-center text-sm text-gray-400">記録がありません</div>
            <ul v-else class="divide-y">
              <li v-for="c in committees" :key="c.id" class="px-5 py-3 text-sm">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-gray-600 text-xs">{{ fmtDate(c.held_at) }}</span>
                  <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700">{{ COMMITTEE_TYPE[c.type] }}</span>
                  <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ COMMITTEE_CATEGORY[c.category] }}</span>
                  <span v-if="c.attendees?.length" class="text-xs text-gray-400">出席 {{ c.attendees.length }}名</span>
                </div>
                <p v-if="c.minutes" class="text-xs text-gray-500 mt-1 line-clamp-2 whitespace-pre-wrap">{{ c.minutes }}</p>
              </li>
            </ul>
          </div>
        </template>

        <!-- ═══ 身体拘束記録 ═══ -->
        <template v-if="activeTab === 'restraint'">
          <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700">
                身体拘束の記録
                <span class="ml-2 text-xs font-normal text-gray-400">緊急やむを得ない場合の態様・時間・理由の記録は義務です</span>
              </h3>
              <button @click="showRestraintForm = !showRestraintForm"
                class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600">
                {{ showRestraintForm ? '閉じる' : '＋ 記録を追加' }}
              </button>
            </div>

            <div v-if="showRestraintForm" class="p-5 border-b bg-indigo-50/40 space-y-3">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div>
                  <label :class="labelClass">児童 <span class="text-red-500">*</span></label>
                  <select v-model="restraintForm.child_id" :class="inputClass">
                    <option value="">選択...</option>
                    <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div>
                  <label :class="labelClass">実施日時 <span class="text-red-500">*</span></label>
                  <input v-model="restraintForm.occurred_at" type="datetime-local" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">実施時間（分）</label>
                  <input v-model.number="restraintForm.duration_minutes" type="number" min="1" :class="inputClass" />
                </div>
              </div>
              <div>
                <label :class="labelClass">拘束の態様 <span class="text-red-500">*</span></label>
                <textarea v-model="restraintForm.method" rows="2" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">緊急やむを得ない理由（切迫性・非代替性・一時性） <span class="text-red-500">*</span></label>
                <textarea v-model="restraintForm.reason" rows="2" :class="inputClass" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label :class="labelClass">保護者への報告日時（報告済みの場合）</label>
                  <input v-model="restraintForm.guardian_notified_at" type="datetime-local" :class="inputClass" />
                </div>
              </div>
              <div class="flex justify-end">
                <button @click="storeRestraint" class="px-5 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">登録</button>
              </div>
            </div>

            <div v-if="restraints.length === 0" class="py-10 text-center text-sm text-gray-400">記録がありません</div>
            <ul v-else class="divide-y">
              <li v-for="r in restraints" :key="r.id" class="px-5 py-3 text-sm">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium">{{ r.child?.name }}</span>
                  <span class="text-gray-500 text-xs">{{ fmtDateTime(r.occurred_at) }}</span>
                  <span v-if="r.duration_minutes" class="text-xs text-gray-500">{{ r.duration_minutes }}分</span>
                  <span v-if="r.guardian_notified_at" class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">
                    保護者報告済 {{ fmtDateTime(r.guardian_notified_at) }}
                  </span>
                  <button v-else @click="markNotified(r)"
                    class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700 hover:bg-red-200">
                    ⚠ 保護者未報告 — 報告済みにする
                  </button>
                </div>
                <p class="text-xs text-gray-500 mt-1"><span class="font-medium">態様：</span>{{ r.method }}</p>
                <p class="text-xs text-gray-500"><span class="font-medium">理由：</span>{{ r.reason }}</p>
              </li>
            </ul>
          </div>
        </template>

        <!-- ═══ BCP・安全計画 ═══ -->
        <template v-if="activeTab === 'bcp'">
          <div v-for="(label, type) in BCP_TYPE" :key="type" class="bg-white shadow-sm rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
              <h3 class="text-sm font-semibold text-gray-700">{{ label }}</h3>
              <span v-if="!bcpForms[type].established_at" class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">未策定（減算対象）</span>
              <span v-else class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">策定済</span>
            </div>
            <div class="grid grid-cols-3 gap-3 items-end">
              <div>
                <label :class="labelClass">策定日</label>
                <input v-model="bcpForms[type].established_at" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">最終見直し日</label>
                <input v-model="bcpForms[type].last_reviewed_at" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">最終研修・訓練日</label>
                <input v-model="bcpForms[type].last_training_at" type="date" :class="inputClass" />
              </div>
            </div>
            <div class="flex justify-end mt-3">
              <button @click="saveBcp(type)" class="px-5 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">保存</button>
            </div>
          </div>

          <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
              <h3 class="text-sm font-semibold text-gray-700">安全計画（{{ currentFiscalYear }}年度）</h3>
              <span v-if="!safetyForm.established_at" class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">未策定</span>
              <span v-else class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">策定済</span>
            </div>
            <div class="grid grid-cols-3 gap-3 items-end">
              <div>
                <label :class="labelClass">年度</label>
                <input v-model="safetyForm.fiscal_year" type="text" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">策定日</label>
                <input v-model="safetyForm.established_at" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">最終見直し日</label>
                <input v-model="safetyForm.last_reviewed_at" type="date" :class="inputClass" />
              </div>
            </div>
            <div class="flex justify-end mt-3">
              <button @click="saveSafety" class="px-5 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">保存</button>
            </div>
            <div v-if="safetyPlans.length > 1" class="mt-4 pt-3 border-t text-xs text-gray-500">
              過年度: <span v-for="p in safetyPlans.filter(p => p.fiscal_year !== currentFiscalYear)" :key="p.id" class="mr-3">
                {{ p.fiscal_year }}年度（策定 {{ fmtDate(p.established_at) }}）
              </span>
            </div>
          </div>
        </template>

        <!-- ═══ 自己評価公表 ═══ -->
        <template v-if="activeTab === 'selfeval'">
          <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
              <h3 class="text-sm font-semibold text-gray-700">自己評価結果の公表（{{ currentFiscalYear }}年度）</h3>
              <span v-if="!selfEvalForm.published_at" class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">未公表（減算対象）</span>
              <span v-else class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">公表済</span>
            </div>
            <p class="text-xs text-gray-500 mb-3">
              保護者評価・自己評価を年1回以上実施し、結果をおおむね1年に1回以上公表することが義務付けられています。
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
              <div>
                <label :class="labelClass">年度</label>
                <input v-model="selfEvalForm.fiscal_year" type="text" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">保護者評価 実施日</label>
                <input v-model="selfEvalForm.guardian_survey_at" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">公表日</label>
                <input v-model="selfEvalForm.published_at" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">公表先URL</label>
                <input v-model="selfEvalForm.published_url" type="url" :class="inputClass" placeholder="https://..." />
              </div>
            </div>
            <div class="flex justify-end mt-3">
              <button @click="saveSelfEval" class="px-5 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">保存</button>
            </div>
            <div v-if="selfEvaluations.length > 1" class="mt-4 pt-3 border-t text-xs text-gray-500">
              過年度: <span v-for="e in selfEvaluations.filter(e => e.fiscal_year !== currentFiscalYear)" :key="e.id" class="mr-3">
                {{ e.fiscal_year }}年度（公表 {{ fmtDate(e.published_at) }}）
              </span>
            </div>
          </div>
        </template>

      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
