<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  schools: Array,
  schoolTypeLabels: Object,
})

const TYPE_COLORS = {
  elementary:    'bg-blue-100 text-blue-700',
  junior_high:   'bg-green-100 text-green-700',
  special_needs: 'bg-purple-100 text-purple-700',
  other:         'bg-gray-100 text-gray-600',
}

const destroy = (school) => {
  if (confirm(`「${school.name}」を削除しますか？\n在籍している児童がいる場合は削除できません。`)) {
    Inertia.delete(route('schools.destroy', school.id))
  }
}
</script>

<template>
  <Head title="学校管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">学校管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />

          <div class="flex justify-end mb-6">
            <Link
              :href="route('schools.create')"
              class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600"
            >＋ 学校登録</Link>
          </div>

          <table v-if="schools.length > 0" class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="pb-2 font-medium">学校名</th>
                <th class="pb-2 font-medium">種別</th>
                <th class="pb-2 font-medium">通常下校</th>
                <th class="pb-2 font-medium">早退下校</th>
                <th class="pb-2 font-medium">住所</th>
                <th class="pb-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="school in schools" :key="school.id" class="hover:bg-gray-50">
                <td class="py-3 pr-4">
                  <div class="font-medium text-gray-900">{{ school.name }}</div>
                  <div v-if="school.name_kana" class="text-xs text-gray-400">{{ school.name_kana }}</div>
                </td>
                <td class="py-3 pr-4">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', TYPE_COLORS[school.school_type] ?? 'bg-gray-100 text-gray-600']">
                    {{ schoolTypeLabels[school.school_type] ?? school.school_type }}
                  </span>
                </td>
                <td class="py-3 pr-4 text-gray-600">{{ school.end_time_regular ?? '—' }}</td>
                <td class="py-3 pr-4 text-gray-600">{{ school.end_time_early ?? '—' }}</td>
                <td class="py-3 pr-4 text-gray-500 text-xs">{{ school.address ?? '—' }}</td>
                <td class="py-3 text-right whitespace-nowrap">
                  <Link
                    :href="route('schools.edit', school.id)"
                    class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600 mr-2"
                  >編集</Link>
                  <button
                    @click="destroy(school)"
                    class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded hover:bg-red-50"
                  >削除</button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="text-center text-gray-400 py-8">
            学校が登録されていません。「＋ 学校登録」から追加してください。
          </p>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
