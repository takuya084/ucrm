<script setup>
defineProps({ state: Object })
</script>
<template>
  <div v-if="state" class="flex flex-wrap gap-2 text-xs" aria-label="連絡帳の状態">
    <span class="rounded-md border px-2 py-1 bg-gray-50">{{ state.publication }}</span>
    <span v-if="state.delivery" class="rounded-md border px-2 py-1 bg-blue-50 text-blue-800">{{ state.delivery }}</span>
    <span v-if="state.confirmation" class="rounded-md border px-2 py-1 bg-green-50 text-green-800">{{ state.confirmation }}</span>
  </div>
</template>
