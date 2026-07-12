<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  child:      Object,
  lastRecord: Object,
  insights:   Object,  // 対象期間の支援記録・連絡帳の集計
})

const today = new Date().toISOString().slice(0, 10)

const form = reactive({
  monitoring_date:     today,
  period_from:         props.lastRecord?.period_to ?? props.insights?.period_from ?? '',
  period_to:           props.insights?.period_to ?? '',
  support_summary:     '',
  strengths:           '',
  challenges:          '',
  guardian_needs:      '',
  environmental_notes: '',
  next_review_date:    '',
})

const CONDITION_META = {
  good:   { label: '良好', class: 'bg-green-400' },
  normal: { label: '普通', class: 'bg-blue-400' },
  poor:   { label: '不調', class: 'bg-red-400' },
}

const maxDomainCount = () =>
  Math.max(1, ...Object.values(props.insights?.domain_counts ?? { x: 1 }))

// 保護者コメントを「保護者のニーズ」欄に引用する
const quoteComment = (c) => {
  const line = `（${c.date} 連絡帳）${c.comment}`
  form.guardian_needs = form.guardian_needs ? form.guardian_needs + '\n' + line : line
}

const store = () => {
  router.post(route('children.monitoring.store', props.child.id), form)
}

const aiLoading = ref(false)
const aiError   = ref('')

const generateDraft = async () => {
  aiLoading.value = true
  aiError.value   = ''
  try {
    const res = await fetch(route('ai-draft.monitoring', props.child.id), {
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
    if (data.support_summary) form.support_summary = data.support_summary
    if (data.strengths)       form.strengths       = data.strengths
    if (data.challenges)      form.challenges      = data.challenges
    if (data.guardian_needs)  form.guardian_needs  = data.guardian_needs
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
  <Head :title="child.name + ' - モニタリング記録'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">モニタリング記録 — {{ child.name }}</h2>
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

          <!-- 前回記録 -->
          <div v-if="lastRecord" class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
            <span class="font-medium text-blue-700">前回モニタリング：</span>
            <span class="text-blue-800">{{ lastRecord.monitoring_date }}（対象期間 {{ lastRecord.period_from }} 〜 {{ lastRecord.period_to }}）</span>
          </div>

          <!-- 期間の記録サマリー（支援記録・連絡帳の集計） -->
          <div v-if="insights && (insights.record_count > 0 || insights.note_count > 0)"
            class="mb-6 border border-indigo-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2 bg-indigo-50 border-b border-indigo-200 text-sm font-medium text-indigo-700">
              📊 期間の記録サマリー（{{ insights.period_from }} 〜 {{ insights.period_to }}
              ／ 支援記録 {{ insights.record_count }}件・連絡帳 {{ insights.note_count }}件）
            </div>
            <div class="p-4 space-y-4 text-sm">

              <!-- 様子の分布と推移 -->
              <div v-if="insights.record_count > 0">
                <p class="text-xs font-medium text-gray-500 mb-1.5">様子の分布</p>
                <div class="flex items-center gap-4 mb-2">
                  <span v-for="(meta, key) in CONDITION_META" :key="key" class="flex items-center gap-1.5 text-xs text-gray-600">
                    <span :class="['w-2.5 h-2.5 rounded-full inline-block', meta.class]"></span>
                    {{ meta.label }} {{ insights.condition_counts[key] }}件
                  </span>
                </div>
                <div class="flex gap-0.5 flex-wrap" title="日ごとの様子（左が古い）">
                  <span
                    v-for="(t, i) in insights.condition_timeline" :key="i"
                    :class="['w-3 h-3 rounded-sm inline-block', CONDITION_META[t.condition]?.class ?? 'bg-gray-200']"
                    :title="`${t.date} ${CONDITION_META[t.condition]?.label ?? ''}`"
                  ></span>
                </div>
              </div>

              <!-- 5領域タグ分布 -->
              <div v-if="insights.note_count > 0">
                <p class="text-xs font-medium text-gray-500 mb-1.5">5領域タグの分布（連絡帳から）</p>
                <div class="space-y-1">
                  <div v-for="(count, key) in insights.domain_counts" :key="key" class="flex items-center gap-2">
                    <span class="w-44 text-xs text-gray-600 shrink-0">{{ insights.domain_labels[key] }}</span>
                    <div class="flex-1 bg-gray-100 rounded h-3 overflow-hidden">
                      <div class="h-3 bg-emerald-400 rounded" :style="{ width: (count / maxDomainCount() * 100) + '%' }"></div>
                    </div>
                    <span class="w-8 text-xs text-gray-500 text-right">{{ count }}</span>
                  </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">記録が少ない領域は、計画どおり支援が提供できているか確認してください。</p>
              </div>

              <!-- 短期目標への手応え -->
              <div v-if="insights.goal_progress_counts.achieved + insights.goal_progress_counts.partial + insights.goal_progress_counts.difficult > 0">
                <p class="text-xs font-medium text-gray-500 mb-1.5">短期目標への手応え</p>
                <div class="flex items-center gap-4 text-xs text-gray-600">
                  <span v-for="(label, key) in insights.goal_progress_labels" :key="key">
                    {{ label }}：<span class="font-bold">{{ insights.goal_progress_counts[key] }}件</span>
                  </span>
                </div>
              </div>

              <!-- 保護者コメント（引用候補） -->
              <div v-if="insights.guardian_comments?.length">
                <p class="text-xs font-medium text-gray-500 mb-1.5">保護者の連絡帳コメント（クリックで「保護者のニーズ」欄に引用）</p>
                <div class="space-y-1 max-h-40 overflow-y-auto">
                  <button
                    v-for="(c, i) in insights.guardian_comments" :key="i"
                    type="button"
                    @click="quoteComment(c)"
                    class="w-full text-left text-xs p-2 bg-amber-50 border border-amber-200 rounded hover:bg-amber-100 transition-colors"
                  >
                    <span class="text-amber-600 font-medium">{{ c.date }}</span>
                    <span class="text-gray-700 ml-1">{{ c.comment }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <form @submit.prevent="store" class="space-y-5">

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label :class="labelClass">実施日 <span class="text-red-500">*</span></label>
                <input v-model="form.monitoring_date" type="date" :class="inputClass" required />
              </div>
              <div>
                <label :class="labelClass">対象期間（開始）</label>
                <input v-model="form.period_from" type="date" :class="inputClass" />
              </div>
              <div>
                <label :class="labelClass">対象期間（終了）</label>
                <input v-model="form.period_to" type="date" :class="inputClass" />
              </div>
            </div>

            <div>
              <label :class="labelClass">支援の経過まとめ</label>
              <textarea v-model="form.support_summary" :class="inputClass" rows="4"
                placeholder="この期間の支援状況、変化、特記事項など" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">強み・できるようになったこと</label>
                <textarea v-model="form.strengths" :class="inputClass" rows="3"
                  placeholder="成長が見られた点、得意なことなど" />
              </div>
              <div>
                <label :class="labelClass">課題・継続支援が必要なこと</label>
                <textarea v-model="form.challenges" :class="inputClass" rows="3"
                  placeholder="引き続き支援が必要な点など" />
              </div>
            </div>

            <div>
              <label :class="labelClass">保護者のニーズ・希望</label>
              <textarea v-model="form.guardian_needs" :class="inputClass" rows="2"
                placeholder="保護者から聞いた要望、希望する支援など" />
            </div>

            <div>
              <label :class="labelClass">環境・家庭状況</label>
              <textarea v-model="form.environmental_notes" :class="inputClass" rows="2"
                placeholder="家庭環境の変化、学校での様子など" />
            </div>

            <div>
              <label :class="labelClass">次回モニタリング予定日</label>
              <input v-model="form.next_review_date" type="date" :class="inputClass" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                記録を保存
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
