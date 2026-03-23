<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import Pagination from '@/Components/Pagination.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  children: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

const searchChildren = () => {
  Inertia.get(route('children.index'), {
    search: search.value,
    status: status.value,
  }, { preserveState: true, replace: true })
}

const CONTRACT_STATUS = {
  active:    { label: '契約中',   class: 'bg-green-100 text-green-800' },
  suspended: { label: '一時停止', class: 'bg-yellow-100 text-yellow-800' },
  ended:     { label: '契約終了', class: 'bg-gray-100 text-gray-600' },
}

const GENDER = { male: '男', female: '女', other: '他' }
</script>

<template>
  <Head title="利用児童一覧" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">利用児童一覧</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <FlashMessage />

            <!-- 検索バー -->
            <div class="flex flex-wrap gap-2 mb-6 items-center">
              <input
                v-model="search"
                type="text"
                placeholder="名前・カナで検索"
                class="border border-gray-300 rounded px-3 py-2 text-sm w-56"
                @keyup.enter="searchChildren"
              />
              <select
                v-model="status"
                class="border border-gray-300 rounded px-3 py-2 text-sm"
                @change="searchChildren"
              >
                <option value="">すべて</option>
                <option value="active">契約中</option>
                <option value="suspended">一時停止</option>
                <option value="ended">契約終了</option>
              </select>
              <button
                @click="searchChildren"
                class="bg-indigo-500 text-white px-4 py-2 rounded text-sm hover:bg-indigo-600"
              >検索</button>
              <Link
                v-if="['admin','leader'].includes($page.props.auth.staff_role)"
                :href="route('children.create')"
                class="ml-auto bg-green-500 text-white px-4 py-2 rounded text-sm hover:bg-green-600"
              >＋ 新規登録</Link>
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                  <tr>
                    <th class="px-4 py-3">児童名</th>
                    <th class="px-4 py-3">カナ</th>
                    <th class="px-4 py-3">性別</th>
                    <th class="px-4 py-3">学年</th>
                    <th class="px-4 py-3">学校</th>
                    <th class="px-4 py-3">契約状況</th>
                    <th class="px-4 py-3"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="child in children.data"
                    :key="child.id"
                    class="border-b hover:bg-gray-50"
                  >
                    <td class="px-4 py-3 font-medium text-gray-900">
                      <Link :href="route('children.show', child.id)" class="hover:underline text-indigo-600">
                        {{ child.name }}
                      </Link>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ child.name_kana ?? '―' }}</td>
                    <td class="px-4 py-3">{{ GENDER[child.gender] ?? '―' }}</td>
                    <td class="px-4 py-3">{{ child.grade ?? '―' }}</td>
                    <td class="px-4 py-3">{{ child.school?.name ?? '―' }}</td>
                    <td class="px-4 py-3">
                      <span
                        v-if="CONTRACT_STATUS[child.contract_status]"
                        :class="['px-2 py-1 rounded-full text-xs font-medium', CONTRACT_STATUS[child.contract_status].class]"
                      >{{ CONTRACT_STATUS[child.contract_status].label }}</span>
                    </td>
                    <td class="px-4 py-3">
                      <Link v-if="['admin','leader'].includes($page.props.auth.staff_role)" :href="route('children.edit', child.id)" class="text-sm text-gray-500 hover:text-indigo-600">編集</Link>
                    </td>
                  </tr>
                  <tr v-if="children.data.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">該当する児童がいません</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <Pagination class="mt-6" :links="children.links" />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
