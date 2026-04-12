<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  returns:  Object,
  children: Array,
})

const fmt = (n) => Number(n).toLocaleString()

const STATUS_COLOR = {
  returned: 'bg-red-100 text-red-700', resubmitting: 'bg-yellow-100 text-yellow-700',
  resubmitted: 'bg-blue-100 text-blue-700', resolved: 'bg-green-100 text-green-700',
}
const STATUS_LABEL = { returned: '返戻', resubmitting: '再請求準備中', resubmitted: '再請求済', resolved: '解決済' }

const showForm = ref(false)
const form = ref({
  year_month: '', child_id: '', return_code: '', return_reason: '',
  original_amount: 0, received_at: new Date().toISOString().slice(0, 10),
})

const submitReturn = () => {
  Inertia.post(route('billing.returns.store'), form.value, {
    onSuccess: () => { showForm.value = false }
  })
}

const resubmit = (id) => {
  if (!confirm('再請求処理を開始しますか？')) return
  Inertia.post(route('billing.returns.resubmit', id))
}
</script>

<template>
  <Head title="返戻管理" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800">返戻管理</h2>
        <div class="flex gap-2">
          <Link :href="route('billing.index')" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">請求管理へ</Link>
          <button @click="showForm = !showForm" class="px-4 py-1.5 text-xs bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">
            {{ showForm ? '閉じる' : '返戻登録' }}
          </button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <!-- 返戻登録フォーム -->
        <div v-if="showForm" class="bg-white shadow-sm rounded-lg p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">返戻登録</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">対象年月</label>
              <input v-model="form.year_month" type="month" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">児童</label>
              <select v-model="form.child_id" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="">選択してください</option>
                <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">返戻コード</label>
              <input v-model="form.return_code" type="text" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">元請求額</label>
              <input v-model.number="form.original_amount" type="number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">受領日</label>
              <input v-model="form.received_at" type="date" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs text-gray-500 mb-1">返戻理由</label>
              <textarea v-model="form.return_reason" rows="2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"></textarea>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <button @click="submitReturn" class="px-6 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600 transition">登録</button>
          </div>
        </div>

        <!-- 返戻一覧 -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div v-if="returns.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
            返戻データがありません
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
              <tr>
                <th class="px-4 py-2 text-left">児童名</th>
                <th class="px-4 py-2 text-left">年月</th>
                <th class="px-4 py-2 text-left">コード</th>
                <th class="px-4 py-2 text-right">元請求額</th>
                <th class="px-4 py-2 text-left">ステータス</th>
                <th class="px-4 py-2 text-left">受領日</th>
                <th class="px-4 py-2 text-right">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="ret in returns.data" :key="ret.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ ret.child?.name }}</td>
                <td class="px-4 py-3 text-xs">{{ ret.year_month }}</td>
                <td class="px-4 py-3 text-xs font-mono">{{ ret.return_code }}</td>
                <td class="px-4 py-3 text-right">{{ fmt(ret.original_amount) }}円</td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLOR[ret.status]]">
                    {{ STATUS_LABEL[ret.status] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ ret.received_at }}</td>
                <td class="px-4 py-3 text-right">
                  <button v-if="ret.status === 'returned'" @click="resubmit(ret.id)"
                    class="text-indigo-600 hover:underline text-xs">再請求</button>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="returns.last_page > 1" class="px-5 py-3 border-t flex gap-2 text-sm">
            <Link v-for="link in returns.links" :key="link.label" :href="link.url ?? '#'" v-html="link.label"
              :class="['px-3 py-1 border rounded', link.active ? 'bg-indigo-500 text-white border-indigo-500' : 'border-gray-300 text-gray-600 hover:bg-gray-50', !link.url ? 'opacity-40 pointer-events-none' : '']" />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
