<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  staffMembers: Array,
  roleLabels: Object,
  qualificationTypes: Object,
})

const ROLE_COLORS = {
  admin:  'bg-red-100 text-red-700',
  leader: 'bg-blue-100 text-blue-700',
  staff:  'bg-gray-100 text-gray-600',
}

const QUAL_COLORS = {
  blue:   'bg-blue-50 text-blue-700 border-blue-200',
  green:  'bg-green-50 text-green-700 border-green-200',
  purple: 'bg-purple-50 text-purple-700 border-purple-200',
  orange: 'bg-orange-50 text-orange-700 border-orange-200',
  gray:   'bg-gray-50 text-gray-600 border-gray-200',
}

const destroy = (staff) => {
  if (confirm(`「${staff.name}」を削除しますか？`)) {
    router.delete(route('staff.destroy', staff.id))
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
                <th class="pb-2 font-medium">保有資格</th>
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
                  <div v-if="member.qualifications?.length" class="flex flex-wrap gap-1">
                    <span v-for="q in member.qualifications" :key="q.id"
                      :class="['px-1.5 py-0.5 rounded text-[10px] border font-medium',
                               QUAL_COLORS[qualificationTypes[q.qualification]?.color] ?? QUAL_COLORS.gray]">
                      {{ qualificationTypes[q.qualification]?.name ?? q.qualification }}
                    </span>
                  </div>
                  <span v-else class="text-gray-300 text-xs">—</span>
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
