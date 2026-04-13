<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'
import Form from './_Form.vue'

const props = defineProps({
  externalFacility:    Object,
  serviceTypeLabels:   Object,
  satelliteTypeLabels: Object,
})

const form = reactive({
  service_type:    props.externalFacility.service_type,
  facility_number: props.externalFacility.facility_number,
  name:            props.externalFacility.name,
  name_kana:       props.externalFacility.name_kana ?? '',
  satellite_type:  props.externalFacility.satellite_type,
  phone:           props.externalFacility.phone ?? '',
  fax:             props.externalFacility.fax ?? '',
  postal_code:     props.externalFacility.postal_code ?? '',
  address:         props.externalFacility.address ?? '',
  notes:           props.externalFacility.notes ?? '',
})

const update = () => {
  Inertia.patch(route('external-facilities.update', props.externalFacility.id), form)
}
</script>

<template>
  <Head title="他社事業所編集" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('external-facilities.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">他社事業所編集</h2>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <BreezeValidationErrors class="mb-4" />

          <form @submit.prevent="update">
            <Form :form="form" :serviceTypeLabels="serviceTypeLabels" :satelliteTypeLabels="satelliteTypeLabels" />

            <div class="flex justify-end gap-3 pt-6 border-t mt-6">
              <Link :href="route('external-facilities.index')" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                キャンセル
              </Link>
              <button type="submit" class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600">
                更新する
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
