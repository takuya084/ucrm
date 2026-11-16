<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  date: String,
  visits: Array,
  mine: Array,
  canManage: Boolean,
  assignments: Array,
  staff: Array,
})

const directions = { pickup: '迎え', dropoff: '送り' }
const locations = { school: '学校', home: '自宅', parents: '親送迎', others: 'その他' }
const visitById = computed(() => new Map(props.visits.map((visit) => [visit.childId, visit])))
const staffById = computed(() => new Map(props.staff.map((person) => [person.id, person.name])))
const time = (value) => value?.slice(0, 5) || ''
const form = useForm({
  service_date: props.date,
  child_id: '',
  direction: 'pickup',
  staff_id: null,
  scheduled_time: '',
  location: '',
})
const saved = ref(false)

watch([() => form.child_id, () => form.direction], () => {
  saved.value = false
  form.clearErrors()
  const assigned = props.assignments.find((row) => row.child_id === form.child_id && row.direction === form.direction)
  const visit = visitById.value.get(form.child_id)
  const pickup = form.direction === 'pickup'
  const reservationLocation = pickup ? visit?.pickupLocation : visit?.dropoffLocation
  form.staff_id = assigned?.staff_id ?? null
  form.scheduled_time = time(assigned ? assigned.scheduled_time : pickup ? visit?.pickup : visit?.dropoff)
  form.location = assigned ? assigned.location ?? '' : locations[reservationLocation] ?? reservationLocation ?? ''
})

const save = () => {
  saved.value = false
  form.put(route('dashboard.transport-assignment'), {
    preserveScroll: true,
    onSuccess: () => { saved.value = true },
  })
}
</script>

<template>
  <section aria-labelledby="my-transport" class="rounded-xl border bg-white">
    <h3 id="my-transport" class="border-b px-4 py-3 font-semibold text-gray-900">自分の送迎</h3>
    <p v-if="mine.length === 0" class="px-4 py-4 text-sm text-gray-700">担当の送迎は登録されていません。</p>
    <ul v-else class="divide-y">
      <li v-for="row in mine" :key="`${row.child_id}-${row.direction}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm">
        <strong>{{ directions[row.direction] }} {{ time(row.scheduled_time) || '時刻未登録' }}</strong>
        <span>{{ visitById.get(row.child_id)?.name }}</span>
        <span class="break-words text-gray-700">{{ row.location || '場所未登録' }}</span>
      </li>
    </ul>

    <details v-if="canManage" class="border-t">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-primary-700">送迎担当を割り当てる</summary>
      <div class="space-y-4 px-4 pb-4">
        <p v-if="visits.length === 0" class="text-sm text-gray-700">この日の利用児童が表示されると担当を登録できます。</p>
        <template v-else>
          <ul v-if="assignments.length" class="space-y-1 text-sm text-gray-700" aria-label="登録済みの送迎担当">
            <li v-for="row in assignments" :key="`${row.child_id}-${row.direction}`">
              {{ directions[row.direction] }} {{ time(row.scheduled_time) || '時刻未登録' }} ／
              {{ visitById.get(row.child_id)?.name }} ／ {{ staffById.get(row.staff_id) || '無効な職員' }}
            </li>
          </ul>
          <form class="flex flex-wrap items-end gap-3 text-sm" @submit.prevent="save">
            <label class="space-y-1">児童
              <select v-model="form.child_id" required :disabled="form.processing" class="block max-w-full rounded-md border-gray-300 text-sm">
                <option value="" disabled>選択してください</option>
                <option v-for="visit in visits" :key="visit.childId" :value="visit.childId">{{ visit.name }}</option>
              </select>
            </label>
            <label class="space-y-1">迎え・送り
              <select v-model="form.direction" :disabled="form.processing" class="block rounded-md border-gray-300 text-sm">
                <option v-for="(label, value) in directions" :key="value" :value="value">{{ label }}</option>
              </select>
            </label>
            <label class="space-y-1">担当職員
              <select v-model="form.staff_id" :disabled="form.processing" class="block max-w-full rounded-md border-gray-300 text-sm">
                <option :value="null">担当なし</option>
                <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
              </select>
            </label>
            <label class="space-y-1">時刻
              <input v-model="form.scheduled_time" type="time" class="block rounded-md border-gray-300 text-sm" />
            </label>
            <label class="space-y-1">場所
              <input v-model="form.location" type="text" maxlength="120" class="block max-w-full rounded-md border-gray-300 text-sm" />
            </label>
            <button type="submit" :disabled="!form.child_id || form.processing"
              class="rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">保存</button>
          </form>
          <p v-if="saved" role="status" class="text-sm text-green-700">送迎担当を更新しました。</p>
          <ul v-if="form.hasErrors" role="alert" class="text-sm text-red-700">
            <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
          </ul>
        </template>
      </div>
    </details>
  </section>
</template>
