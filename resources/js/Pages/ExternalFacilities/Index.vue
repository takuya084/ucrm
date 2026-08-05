<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  externalFacilities:  Array,
  serviceTypeLabels:   Object,
  satelliteTypeLabels: Object,
})

const TYPE_COLORS = {
  after_school:       'bg-blue-100 text-blue-700',
  child_development:  'bg-green-100 text-green-700',
  visit_support:      'bg-purple-100 text-purple-700',
  home_visit:         'bg-amber-100 text-amber-700',
  other:              'bg-gray-100 text-gray-600',
}

const destroy = (ef) => {
  if (confirm(`「${ef.name}」を削除しますか？`)) {
    router.delete(route('external-facilities.destroy', ef.id))
  }
}
</script>

<template>
  <Head title="他社事業所管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">他社事業所管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 sm:rounded-lg p-6">
          <FlashMessage />

          <div class="flex justify-between items-center mb-6">
            <p class="text-sm text-gray-500">
              併給先（上限管理票の送付先）として登録する他社事業所を管理します。
            </p>
            <Link
              :href="route('external-facilities.create')"
              class="px-4 py-2 text-sm bg-green-500 text-white rounded-md hover:bg-green-600"
            >＋ 事業所登録</Link>
          </div>

          <table v-if="externalFacilities.length > 0" class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="pb-2 font-medium">サービス種類</th>
                <th class="pb-2 font-medium">事業所番号</th>
                <th class="pb-2 font-medium">事業所名</th>
                <th class="pb-2 font-medium">区分</th>
                <th class="pb-2 font-medium">電話</th>
                <th class="pb-2 font-medium">FAX</th>
                <th class="pb-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="ef in externalFacilities" :key="ef.id" class="hover:bg-gray-50">
                <td class="py-3 pr-4">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', TYPE_COLORS[ef.service_type] ?? 'bg-gray-100 text-gray-600']">
                    {{ serviceTypeLabels[ef.service_type] ?? ef.service_type }}
                  </span>
                </td>
                <td class="py-3 pr-4 font-mono text-gray-700">{{ ef.facility_number }}</td>
                <td class="py-3 pr-4">
                  <div class="font-medium text-gray-900">{{ ef.name }}</div>
                  <div v-if="ef.name_kana" class="text-xs text-gray-400">{{ ef.name_kana }}</div>
                </td>
                <td class="py-3 pr-4 text-gray-600">{{ satelliteTypeLabels[ef.satellite_type] }}</td>
                <td class="py-3 pr-4 text-gray-600">{{ ef.phone ?? '—' }}</td>
                <td class="py-3 pr-4 text-gray-600">{{ ef.fax ?? '—' }}</td>
                <td class="py-3 text-right whitespace-nowrap">
                  <Link
                    :href="route('external-facilities.edit', ef.id)"
                    class="text-xs px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50 text-gray-600 mr-2"
                  >編集</Link>
                  <button
                    @click="destroy(ef)"
                    class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded-md hover:bg-red-50"
                  >削除</button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="text-center text-gray-400 py-8">
            他社事業所が登録されていません。「＋ 事業所登録」から追加してください。
          </p>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
