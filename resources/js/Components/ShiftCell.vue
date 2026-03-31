<script setup>
const props = defineProps({
  modelValue: Object,   // { start_time, work_type, note }
  disabled: Boolean,
  labels: Array,        // [{ name, is_off }]
  timeOptions: Array,
})

const emit = defineEmits(['update:modelValue'])

const update = (field, value) => {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const isOff = (type) => {
  if (!type) return false
  const label = props.labels.find(l => l.name === type)
  return label ? label.is_off : false
}
</script>

<template>
  <div class="flex flex-col gap-0.5">
    <select
      :value="modelValue.work_type"
      @change="update('work_type', $event.target.value)"
      :disabled="disabled"
      :class="[
        'w-full text-xs px-1 py-0.5',
        isOff(modelValue.work_type) ? 'bg-gray-100 text-gray-500' : 'bg-transparent text-gray-400',
        disabled ? 'cursor-default' : '',
      ]"
    >
      <option value="" class="text-gray-700">―</option>
      <option v-for="l in labels" :key="l.name" :value="l.name" class="text-gray-700">{{ l.name }}</option>
    </select>
    <select
      v-if="!isOff(modelValue.work_type)"
      :value="modelValue.start_time || ''"
      @change="update('start_time', $event.target.value || null)"
      :disabled="disabled"
      class="w-full text-xs px-1 py-0.5 bg-transparent"
    >
      <option value="">--:--</option>
      <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
    </select>
  </div>
</template>
