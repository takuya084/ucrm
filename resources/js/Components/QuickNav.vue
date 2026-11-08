<script setup>
import { Link, usePage } from '@inertiajs/vue3'
const page = usePage()
const items = [
  ['日別の業務', 'dashboard', 'dashboard'],
  ['出欠・利用実績', 'usage-records.index', 'usage-records.*'],
  ['記録・連絡帳', 'contact-notes.index', 'contact-notes.*'],
  ['療育の実施状況', 'program-progress.index', 'program-progress.*'],
]
</script>
<template>
  <nav v-if="!route().current('inquiries.*')" aria-label="同じ日の業務" class="flex gap-2 flex-wrap">
    <Link v-for="[label, name, pattern] in items" :key="name" :href="route(name, page.props.date ? { date: page.props.date } : {})"
      :aria-current="route().current(pattern) ? 'page' : undefined"
      :class="['px-3 py-2 text-sm rounded-md border', route().current(pattern) ? 'bg-primary-50 border-primary-400 text-primary-800 font-bold' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50']">{{ label }}</Link>
  </nav>
</template>
