<script setup>
import { computed, ref } from 'vue'
const props = defineProps({ id: { type: String, required: true }, title: String, modelValue: { type: Boolean, default: undefined } })
const emit = defineEmits(['update:modelValue'])
const localOpen = ref(false)
const trigger = ref(null)
const panel = ref(null)
const open = computed({ get: () => props.modelValue ?? localOpen.value, set: value => { localOpen.value = value; emit('update:modelValue', value) } })
const setOpen = value => {
  if (!value && panel.value?.contains(document.activeElement)) trigger.value?.focus()
  open.value = value
}
defineExpose({ setOpen, isOpen: () => open.value })
</script>

<template>
  <section class="workspace-disclosure rounded-xl border bg-white">
    <button ref="trigger" type="button" :id="id + '-trigger'" :aria-expanded="open" :aria-controls="id"
      class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
      @click="setOpen(!open)">
      <span><slot name="title">{{ title }}</slot></span>
      <svg aria-hidden="true" class="disclosure-chevron h-4 w-4 shrink-0" :class="{ 'is-open': open }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 7 5 5 5-5" /></svg>
    </button>
    <div ref="panel" :id="id" role="region" :aria-labelledby="id + '-trigger'" :aria-hidden="!open" :inert="!open || undefined"
      class="disclosure-panel" :class="{ 'is-open': open }">
      <div class="disclosure-content"><div class="border-t p-4"><slot /></div></div>
    </div>
  </section>
</template>

<style scoped>
.disclosure-panel{display:grid;grid-template-rows:0fr;visibility:hidden;transition:grid-template-rows .2s ease,visibility 0s linear .2s}
.disclosure-panel.is-open{grid-template-rows:1fr;visibility:visible;transition-delay:0s}
.disclosure-content{min-height:0;overflow:hidden}
.disclosure-chevron{transition:transform .2s ease}
.disclosure-chevron.is-open{transform:rotate(180deg)}
@media(prefers-reduced-motion:reduce){.disclosure-panel,.disclosure-chevron{transition:none}}
</style>
