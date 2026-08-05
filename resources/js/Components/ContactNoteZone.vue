<script setup>
import { ref } from 'vue'

// 連絡帳（保護者公開）ゾーン。支援記録の Create / Edit で共用。
// form は親の reactive フォームの contact_note サブオブジェクトをそのまま受け取り直接書き換える。
const props = defineProps({
  form:               Object,  // { meal_note, health_note, guardian_message, five_domain_tags, goal_progress, publish_now }
  contactNote:        Object,  // 既存の連絡帳（null 可）
  shortTermGoal:      String,  // 有効な個別支援計画の短期目標
  fiveDomainLabels:   Object,
  goalProgressLabels: Object,
  childId:            Number,
  aiContext:          Object,  // AI下書き用の内部記録スナップショット { condition, behavior_note, achievement_note, program_names }
})

const inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300'

const toggleDomainTag = (key) => {
  const idx = props.form.five_domain_tags.indexOf(key)
  if (idx >= 0) props.form.five_domain_tags.splice(idx, 1)
  else props.form.five_domain_tags.push(key)
}

const setGoalProgress = (key) => {
  props.form.goal_progress = props.form.goal_progress === key ? null : key
}

const aiLoading = ref(false)
const aiError   = ref('')

const generateDraft = async () => {
  aiLoading.value = true
  aiError.value   = ''
  try {
    const res = await fetch(route('ai-draft.contact-note', props.childId), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept':       'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(props.aiContext ?? {}),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
      aiError.value = data.error ?? data.message ?? 'AI生成に失敗しました'
      return
    }
    if (data.guardian_message) props.form.guardian_message = data.guardian_message
  } catch {
    aiError.value = '通信エラーが発生しました'
  } finally {
    aiLoading.value = false
  }
}
</script>

<template>
  <section class="border-2 border-green-300 rounded-lg overflow-hidden">
    <!-- ゾーンヘッダ -->
    <div class="flex items-center justify-between px-4 py-2 bg-green-50 border-b border-green-200">
      <div class="flex items-center gap-2">
        <span class="text-green-600 font-bold text-sm">連絡帳</span>
        <span class="text-xs text-green-500">この欄は保護者に公開されます</span>
      </div>
      <span
        v-if="contactNote?.status === 'published'"
        class="text-xs px-2 py-0.5 rounded-full bg-green-500 text-white font-medium"
      >公開済み</span>
    </div>

    <div class="p-4 space-y-4 bg-white">
      <!-- 家庭からの連絡（p-yoyaku で保護者が記入） -->
      <div v-if="contactNote?.guardian_submitted_at" class="p-3 bg-amber-50 border border-amber-200 rounded-md">
        <p class="text-xs font-bold text-amber-700 mb-1">家庭からの連絡</p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-amber-800 mb-1">
          <span v-if="contactNote.home_temperature">体温：{{ contactNote.home_temperature }}℃</span>
          <span v-if="contactNote.home_sleep">睡眠：{{ contactNote.home_sleep }}</span>
          <span v-if="contactNote.home_medication">服薬：{{ contactNote.home_medication }}</span>
          <span v-if="contactNote.home_condition">朝の様子：{{ contactNote.home_condition }}</span>
        </div>
        <p v-if="contactNote.guardian_comment" class="text-sm text-amber-900 whitespace-pre-wrap">{{ contactNote.guardian_comment }}</p>
      </div>

      <!-- 保護者へのメッセージ -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-sm font-medium text-gray-700">保護者へのメッセージ</label>
          <button
            type="button"
            @click="generateDraft"
            :disabled="aiLoading"
            class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50"
          >
            <span v-if="aiLoading" class="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ aiLoading ? 'AI生成中...' : '記録からAI下書き' }}
          </button>
        </div>
        <p v-if="aiError" class="text-xs text-red-500 mb-1">{{ aiError }}</p>
        <textarea
          v-model="form.guardian_message" :class="inputClass" rows="4"
          placeholder="今日の様子を保護者向けの表現で記入してください（AI下書きも利用できます）"
        />
      </div>

      <!-- おやつ・食事 / 体調 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">おやつ・食事</label>
          <input v-model="form.meal_note" type="text" :class="inputClass" placeholder="例：完食、おかわりあり" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">体調メモ（事実のみ）</label>
          <input v-model="form.health_note" type="text" :class="inputClass" placeholder="例：来所時にやや眠そう、鼻水あり" />
        </div>
      </div>

      <!-- 5領域タグ -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          5領域タグ
          <span class="text-xs text-gray-400 ml-1">（今日の記録が関わる領域。モニタリング集計に使われます）</span>
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="(label, key) in fiveDomainLabels" :key="key"
            type="button"
            @click="toggleDomainTag(key)"
            :class="[
              'px-3 py-1.5 rounded-full text-xs border transition-all',
              form.five_domain_tags.includes(key)
                ? 'border-green-500 bg-green-500 text-white'
                : 'border-gray-300 text-gray-600 bg-white hover:bg-green-50'
            ]"
          >{{ label }}</button>
        </div>
      </div>

      <!-- 短期目標への手応え -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">短期目標への手応え</label>
        <p v-if="shortTermGoal" class="text-xs text-gray-500 mb-2 p-2 bg-gray-50 rounded-md border border-gray-200">
          目標: {{ shortTermGoal }}
        </p>
        <p v-else class="text-xs text-gray-400 mb-2">有効な個別支援計画がありません（タグのみ記録されます）</p>
        <div class="flex gap-2">
          <button
            v-for="(label, key) in goalProgressLabels" :key="key"
            type="button"
            @click="setGoalProgress(key)"
            :class="[
              'px-3 py-1.5 rounded-md text-xs border transition-all',
              form.goal_progress === key
                ? 'border-green-500 bg-green-100 text-green-700 font-bold'
                : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50'
            ]"
          >{{ label }}</button>
        </div>
      </div>

      <!-- 公開 -->
      <div v-if="contactNote?.status === 'published'" class="text-xs text-gray-500 p-2 bg-gray-50 rounded-md">
        公開済みの連絡帳です。保存すると更新内容が保護者側にも再配信されます（変更は監査ログに記録されます）。
      </div>
      <label v-else class="flex items-center gap-3 cursor-pointer p-3 bg-green-50 border border-green-200 rounded-md">
        <input v-model="form.publish_now" type="checkbox" class="w-4 h-4 accent-green-500" />
        <div>
          <span class="text-sm font-medium text-green-700">保存と同時に保護者へ公開する</span>
          <p class="text-xs text-green-500 mt-0.5">チェックしない場合は下書きとして保存され、連絡帳一覧からあとで公開できます</p>
        </div>
      </label>
    </div>
  </section>
</template>
