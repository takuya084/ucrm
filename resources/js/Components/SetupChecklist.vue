<script setup>
import { ref, onMounted } from 'vue'
const props = defineProps({ items: Array, storageKey: String })
const checked = ref([])
onMounted(() => { try { const value = JSON.parse(localStorage.getItem(props.storageKey) || '[]'); if (Array.isArray(value)) checked.value = value.filter(v => props.items.some(i => i.key === v)) } catch {} })
const save = () => { try { localStorage.setItem(props.storageKey, JSON.stringify(checked.value)) } catch {} }
</script>
<template>
 <details class="rounded-xl border bg-white p-4">
  <summary class="cursor-pointer font-semibold">初めて使うときの準備（{{ checked.length }}/{{ items.length }}）</summary>
  <p class="mt-2 text-sm text-gray-600">確認できた項目にチェック。この端末で準備状況を記録します。</p>
  <ul class="mt-3 space-y-3"><li v-for="item in items" :key="item.key" class="flex items-center gap-3">
   <input type="checkbox" :id="'setup-'+item.key" :value="item.key" v-model="checked" @change="save" :aria-label="item.label+'の準備を確認した'" />
   <a :href="item.href" class="underline">{{ item.label }}</a>
  </li></ul>
 </details>
</template>
