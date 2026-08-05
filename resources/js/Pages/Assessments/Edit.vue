<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  child:      Object,
  assessment: Object,
})

const FIVE_DOMAINS = [
  { key: 'health_life',            label: '健康・生活' },
  { key: 'motor_sensory',          label: '運動・感覚' },
  { key: 'cognition_behavior',     label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations',       label: '人間関係・社会性' },
]

const form = reactive({
  assessed_at:        props.assessment.assessed_at?.slice(0, 10) ?? '',
  physical_condition: props.assessment.physical_condition ?? '',
  living_environment: props.assessment.living_environment ?? '',
  child_intention:    props.assessment.child_intention ?? '',
  guardian_intention: props.assessment.guardian_intention ?? '',
  five_domains:       Object.fromEntries(
    FIVE_DOMAINS.map(d => [d.key, props.assessment.five_domains?.[d.key] ?? ''])
  ),
  notes:              props.assessment.notes ?? '',
})

const update = () => {
  const domains = Object.fromEntries(
    Object.entries(form.five_domains).filter(([, v]) => v && v.trim() !== '')
  )
  router.patch(route('children.assessments.update', [props.child.id, props.assessment.id]), {
    ...form,
    five_domains: Object.keys(domains).length ? domains : null,
  })
}

const inputClass = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - アセスメント編集'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.assessments.show', [child.id, assessment.id])" class="text-gray-400 hover:text-gray-600 text-sm">← 詳細へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">アセスメント 編集 — {{ child.name }}</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update" class="space-y-6">

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">実施情報</h3>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label :class="labelClass">実施日 <span class="text-red-500">*</span></label>
                  <input v-model="form.assessed_at" type="date" :class="inputClass" required />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">状況・意向</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">心身の状況</label>
                  <textarea v-model="form.physical_condition" :class="inputClass" rows="3" />
                </div>
                <div>
                  <label :class="labelClass">生活環境・家庭状況</label>
                  <textarea v-model="form.living_environment" :class="inputClass" rows="3" />
                </div>
                <div>
                  <label :class="labelClass">本人の意向</label>
                  <textarea v-model="form.child_intention" :class="inputClass" rows="2" />
                </div>
                <div>
                  <label :class="labelClass">保護者の意向</label>
                  <textarea v-model="form.guardian_intention" :class="inputClass" rows="2" />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-1 pb-1 border-b">5領域別の発達状況</h3>
              <div class="space-y-3">
                <div v-for="d in FIVE_DOMAINS" :key="d.key">
                  <label :class="labelClass">{{ d.label }}</label>
                  <textarea v-model="form.five_domains[d.key]" :class="inputClass" rows="2" />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">その他特記事項</h3>
              <textarea v-model="form.notes" :class="inputClass" rows="3" />
            </section>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.assessments.show', [child.id, assessment.id])" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600">
                更新する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
