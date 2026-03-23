<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  staffMembers: Array,
  roleLabels: Object,
})

const ROLE_COLORS = {
  admin:  'bg-red-100 text-red-700',
  leader: 'bg-blue-100 text-blue-700',
  staff:  'bg-gray-100 text-gray-600',
}

const destroy = (staff) => {
  if (confirm(`「${staff.name}」を削除しますか？`)) {
    Inertia.delete(route('staff.destroy', staff.id))
  }
}
</script>

<template>
  <Head title="職員管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">職員管理</h2>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <FlashMessage />

          <div class="flex justify-end mb-6">
            <Link
              :href="route('staff.create')"
              class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600"
            >＋ 職員追加</Link>
          </div>

          <table v-if="staffMembers.length > 0" class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-gray-500">
                <th class="pb-2 font-medium">氏名</th>
                <th class="pb-2 font-medium">メールアドレス</th>
                <th class="pb-2 font-medium">役割</th>
                <th class="pb-2 font-medium">ステータス</th>
                <th class="pb-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="member in staffMembers" :key="member.id" class="hover:bg-gray-50">
                <td class="py-3 pr-4 font-medium text-gray-900">{{ member.name }}</td>
                <td class="py-3 pr-4 text-gray-500">{{ member.user?.email ?? '—' }}</td>
                <td class="py-3 pr-4">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', ROLE_COLORS[member.role] ?? 'bg-gray-100 text-gray-600']">
                    {{ roleLabels[member.role] ?? member.role }}
                  </span>
                </td>
                <td class="py-3 pr-4">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', member.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400']">
                    {{ member.is_active ? '有効' : '無効' }}
                  </span>
                </td>
                <td class="py-3 text-right whitespace-nowrap">
                  <Link
                    :href="route('staff.edit', member.id)"
                    class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600 mr-2"
                  >編集</Link>
                  <button
                    @click="destroy(member)"
                    class="text-xs px-3 py-1 border border-red-200 text-red-400 rounded hover:bg-red-50"
                  >削除</button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="text-center text-gray-400 py-8">
            職員が登録されていません。「＋ 職員追加」から追加してください。
          </p>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
