<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  child:      Object,
  assessment: Object,
})

const DOMAIN_LABELS = {
  health_life:            '健康・生活',
  motor_sensory:          '運動・感覚',
  cognition_behavior:     '認知・行動',
  language_communication: '言語・コミュニケーション',
  social_relations:       '人間関係・社会性',
}

const hasDomains = props.assessment.five_domains && Object.values(props.assessment.five_domains).some(v => v)

const destroy = () => {
  if (confirm('このアセスメントを削除しますか？（削除後も管理者が復元できます）')) {
    router.delete(route('children.assessments.destroy', [props.child.id, props.assessment.id]))
  }
}

const SECTIONS = [
  { key: 'physical_condition', label: '心身の状況' },
  { key: 'living_environment', label: '生活環境・家庭状況' },
  { key: 'child_intention',    label: '本人の意向' },
  { key: 'guardian_intention', label: '保護者の意向' },
]
</script>

<template>
  <Head :title="child.name + ' - アセスメント'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4 flex-wrap">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">アセスメント — {{ assessment.assessed_at?.slice(0, 10) }}</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="flex justify-end gap-2">
          <Link
            :href="route('children.assessments.edit', [child.id, assessment.id])"
            class="px-4 py-2 text-sm bg-primary-500 text-white rounded-md hover:bg-primary-600"
          >編集</Link>
          <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded-md hover:bg-red-50">削除</button>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500">実施日</dt>
              <dd class="font-medium">{{ assessment.assessed_at?.slice(0, 10) }}</dd>
            </div>
            <div v-if="assessment.staff">
              <dt class="text-xs text-gray-500">実施者</dt>
              <dd>{{ assessment.staff?.name }}</dd>
            </div>
          </dl>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
          <template v-for="s in SECTIONS" :key="s.key">
            <div v-if="assessment[s.key]">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ s.label }}</h3>
              <p class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-3 rounded-md">{{ assessment[s.key] }}</p>
            </div>
          </template>
        </div>

        <div v-if="hasDomains" class="bg-white border border-gray-200 rounded-lg p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">5領域別の発達状況</h3>
          <dl class="space-y-3">
            <template v-for="(label, key) in DOMAIN_LABELS" :key="key">
              <div v-if="assessment.five_domains?.[key]">
                <dt class="text-xs font-medium text-gray-600 mb-1">{{ label }}</dt>
                <dd class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-2 rounded-md">{{ assessment.five_domains[key] }}</dd>
              </div>
            </template>
          </dl>
        </div>

        <div v-if="assessment.notes" class="bg-white border border-gray-200 rounded-lg p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">その他特記事項</h3>
          <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ assessment.notes }}</p>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
