<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child: Object,
})

const FIVE_DOMAINS = [
  { key: 'health_life',            label: '健康・生活' },
  { key: 'motor_sensory',          label: '運動・感覚' },
  { key: 'cognition_behavior',     label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations',       label: '人間関係・社会性' },
]

const form = reactive({
  assessed_at:        new Date().toISOString().slice(0, 10),
  physical_condition: '',
  living_environment: '',
  child_intention:    '',
  guardian_intention: '',
  five_domains:       Object.fromEntries(FIVE_DOMAINS.map(d => [d.key, ''])),
  notes:              '',
})

const store = () => {
  const domains = Object.fromEntries(
    Object.entries(form.five_domains).filter(([, v]) => v && v.trim() !== '')
  )
  Inertia.post(route('children.assessments.store', props.child.id), {
    ...form,
    five_domains: Object.keys(domains).length ? domains : null,
  })
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head :title="child.name + ' - アセスメント'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.show', child.id)" class="text-gray-400 hover:text-gray-600 text-sm">← {{ child.name }}</Link>
        <h2 class="font-semibold text-xl text-gray-800">アセスメント 作成 — {{ child.name }}</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <p class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
            アセスメントは個別支援計画作成プロセスの起点です。実施後に個別支援計画の作成・見直しを行ってください。
          </p>

          <form @submit.prevent="store" class="space-y-6">

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
                  <textarea v-model="form.physical_condition" :class="inputClass" rows="3"
                    placeholder="発達の状況、健康状態、服薬など" />
                </div>
                <div>
                  <label :class="labelClass">生活環境・家庭状況</label>
                  <textarea v-model="form.living_environment" :class="inputClass" rows="3"
                    placeholder="家族構成、生活リズム、併用サービスなど" />
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
              <p class="text-xs text-gray-500 mb-3">個別支援計画の「5領域との関連」の根拠になります。</p>
              <div class="space-y-3">
                <div v-for="d in FIVE_DOMAINS" :key="d.key">
                  <label :class="labelClass">{{ d.label }}</label>
                  <textarea v-model="form.five_domains[d.key]" :class="inputClass" rows="2"
                    :placeholder="`「${d.label}」の現在の状況・課題`" />
                </div>
              </div>
            </section>

            <section>
              <h3 class="text-sm font-semibold text-gray-600 mb-3 pb-1 border-b">その他特記事項</h3>
              <textarea v-model="form.notes" :class="inputClass" rows="3" />
            </section>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link :href="route('children.show', child.id)" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                アセスメントを保存
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
