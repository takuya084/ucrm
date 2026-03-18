<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/inertia-vue3'

const page = usePage()
const visible = ref(false)

watch(
  () => page.props.value.flash,
  (flash) => {
    if (flash?.message) {
      visible.value = true
      setTimeout(() => { visible.value = false }, 3000)
    }
  },
  { immediate: true }
)
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-700"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible && $page.props.flash?.status === 'success'"
      class="bg-blue-300 text-white p-4"
    >
      {{ $page.props.flash.message }}
    </div>
  </Transition>
  <Transition
    enter-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-700"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible && $page.props.flash?.status === 'danger'"
      class="bg-red-300 text-white p-4"
    >
      {{ $page.props.flash.message }}
    </div>
  </Transition>
</template>
