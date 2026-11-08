<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
const emit = defineEmits(['navigate'])
const page = usePage()
const role = computed(() => page.props.auth.staff_role)
const day = computed(() => page.props.date ?? page.props.recordDate ?? page.props.todayStats?.date)
const item = (label, name, patterns, roles, params = {}) => ({ label, name, patterns, roles, params })
const groups = computed(() => [
  { label: 'ホーム', items: [item('今日の業務', 'dashboard', ['dashboard'])] },
  { label: '日々の業務', items: [
    item('出欠・利用実績・送迎', 'usage-records.index', ['usage-records.*']),
    item('記録・連絡帳', 'contact-notes.index', ['contact-notes.*', 'support-records.*']),
    item('療育の実施状況', 'program-progress.index', ['program-progress.*']),
    item('空き枠調整', 'vacancy-adjustment.index', ['vacancy-adjustment.*']),
  ] },
  { label: '児童・支援', items: [
    item('児童一覧・過去の記録', 'children.index', ['children.*', 'assessments.*', 'support-plans.*', 'monitoring-records.*']),
    item('支援計画・モニタリング', 'children.index', [], null, { section: 'plan' }),
    item('受給者証・利用契約', 'children.index', [], null, { section: 'cert' }),
  ] },
  { label: '請求・会計', items: [
    item('月次請求', 'billing.index', ['billing.index', 'billing.show', 'billing.details.*'], ['admin','leader']),
    item('前月請求の確認', 'billing.previous-month', ['billing.previous-month'], ['admin','leader']),
    item('実績記録票', 'billing.daily-records.index', ['billing.daily-records.*'], ['admin','leader']),
    item('上限管理', 'billing.cap-management.index', ['billing.cap-management.*'], ['admin','leader']),
    item('利用者請求', 'billing.invoices.index', ['billing.invoices.*'], ['admin','leader']),
    item('返戻管理', 'billing.returns.index', ['billing.returns.*'], ['admin','leader']),
    item('過誤申立', 'billing.error-claims.index', ['billing.error-claims.*'], ['admin','leader']),
    item('経費管理', 'billing.expenses.index', ['billing.expenses.*'], ['admin','leader']),
  ] },
  { label: '事業所運営', items: [
    item('シフト', 'shifts.index', ['shifts.*', 'shift-labels.*']),
    item('職員管理', 'staff.index', ['staff.*'], ['admin']),
    item('運営記録', 'operation-records.index', ['operation-records.*'], ['admin','leader']),
    item('問い合わせ', 'inquiries.index', ['inquiries.*']),
  ] },
  { label: '設定', items: [
    item('施設設定', 'facility.edit', ['facility.*'], ['admin']),
    item('学校', 'schools.index', ['schools.*'], ['admin','leader']),
    item('他社事業所', 'external-facilities.index', ['external-facilities.*'], ['admin','leader']),
    item('療育プログラム', 'programs.index', ['programs.*']),
    item('加算・減算設定', 'billing.settings.service-codes', ['billing.settings.*'], ['admin','leader']),
  ] },
].map(g => ({ ...g, items: g.items.filter(i => !i.roles || i.roles.includes(role.value)) })).filter(g => g.items.length))
const params = i => ({ ...(['dashboard','usage-records.index','contact-notes.index','program-progress.index'].includes(i.name) && day.value ? { date: day.value } : {}), ...i.params })
const active = i => {
  const section = new URL(page.url, 'http://workspace.local').searchParams.get('section')
  if (route().current('children.index')) return i.name === 'children.index' && (i.params.section ?? null) === section
  return i.patterns.some(p => route().current(p))
}
</script>

<template>
  <nav aria-label="業務メニュー" class="space-y-5 px-3 py-5">
    <section v-for="group in groups" :key="group.label" :aria-label="group.label">
      <h2 class="px-3 mb-1 text-xs font-bold tracking-wide text-gray-500">{{ group.label }}</h2>
      <Link v-for="entry in group.items" :key="entry.label" :href="route(entry.name, params(entry))"
        :aria-current="active(entry) ? 'page' : undefined" @click="emit('navigate')"
        :class="['block rounded-lg px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600', active(entry) ? 'bg-primary-50 text-primary-800 font-bold border-l-4 border-primary-500' : 'text-gray-700 hover:bg-gray-100']">
        {{ entry.label }}
      </Link>
    </section>
  </nav>
</template>
