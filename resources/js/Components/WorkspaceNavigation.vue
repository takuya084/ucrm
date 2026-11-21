<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
const icons = { 'ホーム': 'M3 10l9-7 9 7v10H3z M9 20v-7h6v7', '日々の業務': 'M4 5h16v16H4z M8 3v4 M16 3v4 M4 11h16', '児童・支援': 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M17 4a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-4', '請求・会計': 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6 M9 12h6', '事業所運営': 'M4 21V3h16v18 M8 7h2 M14 7h2 M8 11h2 M14 11h2 M9 21v-6h6v6', '設定': 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2' }
const emit = defineEmits(['navigate'])
const page = usePage()
const role = computed(() => page.props.auth.staff_role)
const day = computed(() => page.props.date ?? page.props.recordDate ?? page.props.todayStats?.date)
const item = (label, name, patterns, roles, params = {}) => ({ label, name, patterns, roles, params })
const groups = computed(() => [
  { label: 'ホーム', items: [item('業務内容確認', 'dashboard', ['dashboard'])] },
  { label: '日々の業務', items: [
    item('出欠・利用実績', 'usage-records.index', ['usage-records.*']),
    item('欠席連絡・相談記録', 'absences.index', ['absences.*']),
    item('送迎一覧', 'transport.index', ['transport.*']),
    item('療育予定・年間行事', 'daily-activities.index', ['daily-activities.*'], ['admin','leader']),
    item('記録・連絡帳', 'contact-notes.index', ['contact-notes.*', 'support-records.*']),
    item('療育の実施状況', 'program-progress.index', ['program-progress.*']),
    item('空き枠調整', 'vacancy-adjustment.index', ['vacancy-adjustment.*']),
  ] },
  { label: '児童・支援', items: [
    item('児童の記録', 'children.index', ['children.*', 'assessments.*', 'support-plans.*', 'monitoring-records.*']),
  ] },
  { label: '請求・会計', items: [
    item('月次請求', 'billing.index', ['billing.index', 'billing.show', 'billing.details.*'], ['admin','leader']),
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
const params = i => ({ ...(['dashboard','usage-records.index','absences.index','contact-notes.index','program-progress.index','transport.index','daily-activities.index'].includes(i.name) && day.value ? { date: day.value } : {}), ...i.params })
const active = i => {
  return i.patterns.some(p => route().current(p))
}
</script>

<template>
  <nav aria-label="業務メニュー" class="space-y-5 px-3 py-5">
    <section v-for="group in groups" :key="group.label" :aria-label="group.label">
      <h2 class="workspace-nav-heading"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path :d="icons[group.label]" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ group.label }}</h2>
      <Link v-for="entry in group.items" :key="entry.label" :href="route(entry.name, params(entry))"
        :aria-current="active(entry) ? 'page' : undefined" @click="emit('navigate')"
        :class="['block rounded-lg px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600', active(entry) ? 'bg-primary-50 text-primary-800 font-bold border-l-4 border-primary-500' : 'text-gray-700 hover:bg-gray-100']">
        {{ entry.label }}
      </Link>
    </section>
  </nav>
</template>
