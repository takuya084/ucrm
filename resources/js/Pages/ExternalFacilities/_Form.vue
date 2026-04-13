<script setup>
defineProps({
  form:                Object,
  serviceTypeLabels:   Object,
  satelliteTypeLabels: Object,
})

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <div class="space-y-5">

    <div>
      <label :class="labelClass">サービス種類 <span class="text-red-500">*</span></label>
      <div class="flex flex-wrap gap-2 mt-1">
        <label
          v-for="(label, value) in serviceTypeLabels"
          :key="value"
          :class="[
            'px-3 py-2 border rounded cursor-pointer text-sm transition-colors',
            form.service_type === value
              ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
              : 'border-gray-300 hover:bg-gray-50'
          ]"
        >
          <input type="radio" v-model="form.service_type" :value="value" class="sr-only" />
          {{ label }}
        </label>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label :class="labelClass">事業所番号（10桁） <span class="text-red-500">*</span></label>
        <input v-model="form.facility_number" type="text" maxlength="10" :class="[inputClass, 'font-mono']" placeholder="1312345678" />
      </div>
      <div>
        <label :class="labelClass">サテライト区分 <span class="text-red-500">*</span></label>
        <select v-model="form.satellite_type" :class="inputClass">
          <option v-for="(label, value) in satelliteTypeLabels" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
    </div>

    <div>
      <label :class="labelClass">事業所名 <span class="text-red-500">*</span></label>
      <input v-model="form.name" type="text" :class="inputClass" placeholder="例：○○放課後デイサービス" />
    </div>

    <div>
      <label :class="labelClass">事業所名（かな）</label>
      <input v-model="form.name_kana" type="text" :class="inputClass" />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label :class="labelClass">電話番号</label>
        <input v-model="form.phone" type="text" :class="inputClass" placeholder="03-1234-5678" />
      </div>
      <div>
        <label :class="labelClass">FAX番号</label>
        <input v-model="form.fax" type="text" :class="inputClass" placeholder="03-1234-5679" />
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label :class="labelClass">郵便番号</label>
        <input v-model="form.postal_code" type="text" :class="inputClass" placeholder="150-0001" />
      </div>
      <div class="col-span-2">
        <label :class="labelClass">住所</label>
        <input v-model="form.address" type="text" :class="inputClass" placeholder="東京都渋谷区..." />
      </div>
    </div>

    <div>
      <label :class="labelClass">備考</label>
      <textarea v-model="form.notes" rows="2" :class="inputClass" />
    </div>
  </div>
</template>
