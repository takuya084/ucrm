<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child: Object,
  plan:  Object,
})

const destroy = () => {
  if (confirm('この個別支援計画を削除しますか？')) {
    Inertia.delete(route('children.support-plans.destroy', [props.child.id, props.plan.id]))
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
        <span
          :class="[
            'text-xs font-medium px-2 py-1 rounded-full',
            plan.guardian_agreement ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
          ]"
        >
          {{ plan.guardian_agreement ? '同意済' : '同意待ち' }}
        </span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="flex justify-end gap-2">
          <a
            :href="route('children.support-plans.pdf', [child.id, plan.id])"
            class="px-4 py-2 text-sm bg-emerald-600 text-white rounded hover:bg-emerald-700"
          >PDF出力</a>
          <template v-if="['admin','leader'].includes($page.props.auth.staff_role)">
            <Link
              :href="route('children.support-plans.edit', [child.id, plan.id])"
              class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600"
            >編集</Link>
            <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50">削除</button>
          </template>
        </div>

        <!-- メタ情報 -->
        <div class="bg-white shadow-sm rounded-lg p-5">
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
                <Link :href="route('children.support-plans.show', [child.id, plan.previous_plan.id])" class="text-indigo-600 hover:underline">
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
        <div class="bg-white shadow-sm rounded-lg p-5 space-y-4">
          <div v-if="plan.long_term_goal">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">長期目標</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ plan.long_term_goal }}</p>
          </div>
          <div v-if="plan.short_term_goal">
            <h3 class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">短期目標</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-indigo-50 p-3 rounded">{{ plan.short_term_goal }}</p>
          </div>
          <div v-if="plan.support_policy">
            <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">支援方針</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-blue-50 p-3 rounded">{{ plan.support_policy }}</p>
          </div>
          <div v-if="plan.program_content">
            <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-1">支援内容・プログラム</h3>
            <p class="text-sm text-gray-800 whitespace-pre-wrap bg-green-50 p-3 rounded">{{ plan.program_content }}</p>
          </div>
        </div>

        <!-- 5領域との関連 -->
        <div v-if="hasDomains" class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">5領域との関連</h3>
          <dl class="space-y-3">
            <template v-for="(label, key) in DOMAIN_LABELS" :key="key">
              <div v-if="plan.five_domains?.[key]">
                <dt class="text-xs font-medium text-gray-600 mb-1">{{ label }}</dt>
                <dd class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 p-2 rounded">{{ plan.five_domains[key] }}</dd>
              </div>
            </template>
          </dl>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
