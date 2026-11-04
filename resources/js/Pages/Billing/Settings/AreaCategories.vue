<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  categories: Array,
})

const rows = ref(props.categories.map(c => ({ ...c })))

const hasChanges = computed(() =>
  rows.value.some((r, i) => String(r.unit_price ?? '') !== String(props.categories[i].unit_price ?? ''))
)

const save = () => {
  router.post(route('billing.settings.area-categories.update'), {
    categories: rows.value.map(r => ({ id: r.id, unit_price: r.unit_price === '' ? null : r.unit_price })),
  })
}
</script>

<template>
  <Head title="地域区分マスタ" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('billing.settings.service-codes')" class="text-gray-400 hover:text-gray-600 text-sm">
          ← 加算・減算設定
        </Link>
        <h2 class="font-semibold text-xl text-gray-800">地域区分マスタ</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />

        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 text-sm text-yellow-800">
          級地コードごとの「1単位あたりの単価（円）」を登録します。<br>
          <strong>単価未登録（空欄）の級地は、告示で正式な数値を確認してから入力してください。</strong>
          推測の数値を入れないでください（請求金額に直結します）。
        </div>

        <div class="bg-white border border-gray-200 sm:rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-2 text-left text-xs text-gray-500">コード</th>
                <th class="px-4 py-2 text-left text-xs text-gray-500">級地</th>
                <th class="px-4 py-2 text-left text-xs text-gray-500">単価（円）</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="row in rows" :key="row.id">
                <td class="px-4 py-2 text-gray-500">{{ row.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ row.label }}</td>
                <td class="px-4 py-2">
                  <input
                    v-model="row.unit_price"
                    type="number" step="0.01" min="0" max="20"
                    placeholder="未確認"
                    class="w-28 rounded-md border-gray-300 text-sm"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end">
          <button
            @click="save"
            :disabled="!hasChanges"
            class="px-6 py-2 text-sm text-white bg-primary-500 rounded-md hover:bg-primary-600 disabled:opacity-40"
          >
            保存
          </button>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
