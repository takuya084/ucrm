<script>
export default { inheritAttrs: false };
</script>

<script setup>
import { ref, watch } from 'vue';
import BreezeInput from '@/Components/Input.vue';

const props = defineProps({
    modelValue: String,
    label: { type: String, default: 'パスワード' },
});

defineEmits(['update:modelValue']);

const visible = ref(false);

watch(() => props.modelValue, (value) => {
    if (!value) visible.value = false;
});
</script>

<template>
    <div class="relative">
        <BreezeInput
            v-bind="$attrs"
            :type="visible ? 'text' : 'password'"
            :model-value="modelValue"
            class="block w-full pr-20"
            @update:model-value="$emit('update:modelValue', $event)"
        />
        <button
            type="button"
            :aria-controls="$attrs.id"
            :aria-label="label + 'を表示'"
            :aria-pressed="visible"
            class="absolute inset-y-0 right-0 rounded-r-md px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
            @click="visible = !visible"
        >
            {{ visible ? '非表示' : '表示' }}
        </button>
    </div>
</template>
