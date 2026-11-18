<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { mountTransportGrid, gridRequest } from '../../../public/transport-grid/grid.js'
import '../../../public/transport-grid/grid.css'

const props = defineProps({ date: String, canManage: Boolean })
const container = ref(null)
let cleanup, removeListener
onMounted(() => {
  cleanup = mountTransportGrid(container.value, {
    date: props.date, canManage: props.canManage,
    load: () => gridRequest(route('dashboard.transport-grid', { date: props.date })),
    save: payload => gridRequest(route('dashboard.transport-grid'), 'PUT', payload),
  })
  // Inertia navigation does not fire beforeunload. Keep the same unsaved-edit guard.
  removeListener = router.on('before', event => {
    if (container.value?.querySelector('[data-count]')?.textContent.startsWith('未保存') && !window.confirm('未保存の入力があります。このページを離れますか？')) event.preventDefault()
  })
})
onBeforeUnmount(() => { cleanup?.(); removeListener?.() })
</script>

<template><div ref="container" class="min-w-0" /></template>
