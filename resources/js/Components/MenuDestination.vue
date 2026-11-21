<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({ label: String, href: String, prefix: { type: String, default: '入力先：メニュー' } })
</script>

<template>
  <span class="workspace-menu-destination">{{ prefix }}「<Link :href="href" class="font-semibold text-primary-700 underline decoration-primary-300 underline-offset-4 hover:text-primary-900">{{ label }}</Link>」</span>
</template>
