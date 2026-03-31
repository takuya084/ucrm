<script setup>
import { Link } from '@inertiajs/inertia-vue3'

const navItems = [
  { label: '出席管理',  routeName: 'usage-records.index', pattern: 'usage-records.*' },
  { label: '療育進度',  routeName: 'program-progress.index', pattern: 'program-progress.*' },
  { label: '問い合わせ', routeName: 'inquiries.index', pattern: 'inquiries.*' },
]
</script>

<template>
  <div class="flex gap-2 flex-wrap">
    <Link
      v-for="item in navItems"
      :key="item.routeName"
      :href="route(item.routeName)"
      :class="[
        'px-4 py-2 text-sm rounded transition-colors',
        route().current(item.pattern)
          ? 'bg-gray-200 text-gray-800 font-medium cursor-default'
          : 'border border-gray-300 text-gray-700 hover:bg-gray-50'
      ]"
    >{{ item.label }}</Link>
  </div>
</template>
