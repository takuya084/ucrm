<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive, ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child:  Object,
  record: Object,
})

const form = reactive({
  monitoring_date:     props.record.monitoring_date ?? '',
  period_from:         props.record.period_from ?? '',
  period_to:           props.record.period_to ?? '',
  support_summary:     props.record.support_summary ?? '',
  strengths:           props.record.strengths ?? '',
  challenges:          props.record.challenges ?? '',
  guardian_needs:      props.record.guardian_needs ?? '',
  environmental_notes: props.record.environmental_notes ?? '',
  next_review_date:    props.record.next_review_date ?? '',
})

const update = () => {
  Inertia.patch(route('children.monitoring.update', [props.child.id, props.record.id]), form)
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
  <Head :title="child.name + ' - モニタリング記録編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.monitoring.show', [child.id, record.id])" class="text-gray-400 hover:text-gray-600 text-sm">← 記録詳細へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">モニタリング記録編集 — {{ record.monitoring_date }}</h2>
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

          <form @submit.prevent="update" class="space-y-5">

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
              <textarea v-model="form.support_summary" :class="inputClass" rows="4" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label :class="labelClass">強み・できるようになったこと</label>
                <textarea v-model="form.strengths" :class="inputClass" rows="3" />
              </div>
              <div>
                <label :class="labelClass">課題・継続支援が必要なこと</label>
                <textarea v-model="form.challenges" :class="inputClass" rows="3" />
              </div>
            </div>

            <div>
              <label :class="labelClass">保護者のニーズ・希望</label>
              <textarea v-model="form.guardian_needs" :class="inputClass" rows="2" />
            </div>

            <div>
              <label :class="labelClass">環境・家庭状況</label>
              <textarea v-model="form.environmental_notes" :class="inputClass" rows="2" />
            </div>

            <div>
              <label :class="labelClass">次回モニタリング予定日</label>
              <input v-model="form.next_review_date" type="date" :class="inputClass" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.monitoring.show', [child.id, record.id])" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
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
