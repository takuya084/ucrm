<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({ detail: Object })

const lines = ref(
  (props.detail.billing_detail_lines ?? []).map(l => ({
    id: l.id,
    service_code: l.service_code,
    service_name: l.service_name,
    count: l.count,
    units_per_count: l.units_per_count,
    total_units: l.total_units,
  }))
)

const recalcLine = (line) => {
  line.total_units = line.count * line.units_per_count
}

const save = () => {
  Inertia.patch(route('billing.details.update', props.detail.id), {
    lines: lines.value.map(l => ({
      id: l.id,
      count: l.count,
      units_per_count: l.units_per_count,
      total_units: l.total_units,
    })),
  })
}
</script>

<template>
  <Head :title="`明細編集 - ${detail.child?.name}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">{{ detail.child?.name }} - 明細編集</h2>
        <Link :href="route('billing.details.show', detail.id)" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">キャンセル</Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">サービスコード別内訳（手動調整）</h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">コード</th>
                <th class="px-4 py-2 text-left">サービス名</th>
                <th class="px-4 py-2 text-right">回数</th>
                <th class="px-4 py-2 text-right">単位/回</th>
                <th class="px-4 py-2 text-right">合計単位</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="line in lines" :key="line.id">
                <td class="px-4 py-2 font-mono text-xs">{{ line.service_code }}</td>
                <td class="px-4 py-2">{{ line.service_name }}</td>
                <td class="px-4 py-2 text-right">
                  <input v-model.number="line.count" @input="recalcLine(line)" type="number" min="0"
                    class="w-20 text-right border border-gray-300 rounded px-2 py-1 text-sm" />
                </td>
                <td class="px-4 py-2 text-right">
                  <input v-model.number="line.units_per_count" @input="recalcLine(line)" type="number" min="0"
                    class="w-20 text-right border border-gray-300 rounded px-2 py-1 text-sm" />
                </td>
                <td class="px-4 py-2 text-right font-medium">{{ line.total_units.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
          <div class="px-5 py-4 border-t flex justify-end">
            <button @click="save" class="px-6 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
              保存
            </button>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
