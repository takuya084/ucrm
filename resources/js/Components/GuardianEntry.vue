<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
const props = defineProps({ note: Object, state: Object })
const saving = ref(false)
const error = ref('')
const check = () => {
  if (saving.value) return
  saving.value = true; error.value = ''
  router.post(route('contact-notes.check-guardian', props.note.id), { guardian_revision: props.note.guardian_source_revision ?? 0 }, {
    preserveScroll: true, preserveState: true,
    onError: errors => { error.value = errors.guardian_revision ?? '確認を保存できませんでした。画面を更新して確認してください。' },
    onFinish: () => { saving.value = false },
  })
}
</script>
<template>
  <section id="family-note" class="scroll-mt-4 mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4" aria-label="家庭からの連絡">
    <h3 class="font-bold text-amber-900 mb-2">1. 家庭からの連絡</h3>
    <template v-if="note?.guardian_submitted_at">
      <p class="text-sm font-semibold mb-2">{{ state?.home }}</p>
      <dl class="flex flex-wrap gap-x-5 gap-y-2 text-sm mb-2">
        <div v-if="note.home_temperature"><dt class="inline">体温：</dt><dd class="inline">{{ note.home_temperature }}℃</dd></div>
        <div v-if="note.home_sleep"><dt class="inline">睡眠：</dt><dd class="inline">{{ note.home_sleep }}</dd></div>
        <div v-if="note.home_medication"><dt class="inline">服薬：</dt><dd class="inline">{{ note.home_medication }}</dd></div>
        <div v-if="note.home_condition"><dt class="inline">朝の様子：</dt><dd class="inline">{{ note.home_condition }}</dd></div>
      </dl>
      <p class="text-sm whitespace-pre-wrap">{{ note.guardian_comment || '連絡事項の記入はありません。' }}</p>
      <p v-if="error" role="alert" class="text-sm text-red-700 mt-2">{{ error }}</p>
      <button v-if="state?.homePending" type="button" :disabled="saving" @click="check" class="mt-3 bg-amber-800 text-white rounded-md px-4 py-2 text-sm disabled:opacity-50">{{ saving ? '保存中…' : '家庭からの連絡を確認した' }}</button>
      <p v-else class="text-sm text-green-800 mt-2">事業所確認済み</p>
    </template>
    <p v-else class="text-sm text-gray-700">この日の家庭からの連絡はまだ届いていません。</p>
  </section>
</template>
