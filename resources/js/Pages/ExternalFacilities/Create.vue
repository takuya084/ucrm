<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import Form from './_Form.vue'

defineProps({
  serviceTypeLabels:   Object,
  satelliteTypeLabels: Object,
})

const form = reactive({
  service_type:    'after_school',
  facility_number: '',
  name:            '',
  name_kana:       '',
  satellite_type:  'main',
  phone:           '',
  fax:             '',
  postal_code:     '',
  address:         '',
  notes:           '',
})

const store = () => {
  router.post(route('external-facilities.store'), form)
}
</script>

<template>
  <Head title="他社事業所登録" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('external-facilities.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">他社事業所登録</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="store">
            <Form :form="form" :serviceTypeLabels="serviceTypeLabels" :satelliteTypeLabels="satelliteTypeLabels" />

            <div class="flex justify-end gap-3 pt-6 border-t mt-6">
              <Link :href="route('external-facilities.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                登録する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
