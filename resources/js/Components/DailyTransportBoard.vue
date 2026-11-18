<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  date: String,
  visits: Array,
  canManage: Boolean,
  assignments: Array,
  staff: Array,
  syncState: { type: String, default: 'local' },
  localCount: { type: Number, default: 0 },
})

const shared = computed(() => props.syncState !== 'local')
const selections = computed(() => [...new Map(props.assignments.filter(row => row.direction === form.direction).map(row => [row.reservation_id, row])).values()])
const directions = { pickup: '迎え', dropoff: '送り' }
const locations = { school: '学校', home: '自宅', parents: '親送迎', others: 'その他' }
const visitById = computed(() => new Map(props.visits.map((visit) => [visit.childId, visit])))
const time = (value) => value?.slice(0, 5) || ''
const form = useForm({
  service_date: props.date,
  child_id: '',
  reservation_id: '',
  revision: 0,
  direction: 'pickup',
  staff_id: null,
  scheduled_time: '',
  location: '',
})
const saved = ref(false)
const editor = ref(null)
const assigneeInput = ref(null)

const loadSelection = () => {
  saved.value = false
  form.clearErrors()
  const assigned = props.assignments.find((row) => (shared.value ? row.reservation_id === form.reservation_id : row.child_id === form.child_id) && row.direction === form.direction)
  form.revision = assigned?.revision ?? 0
  const visit = visitById.value.get(form.child_id)
  const pickup = form.direction === 'pickup'
  const reservationLocation = pickup ? visit?.pickupLocation : visit?.dropoffLocation
  form.staff_id = assigned?.staff_id ?? null
  form.scheduled_time = time(assigned ? assigned.scheduled_time : pickup ? visit?.pickup : visit?.dropoff)
  form.location = assigned ? assigned.location ?? '' : locations[reservationLocation] ?? reservationLocation ?? ''
}

watch([() => form.child_id, () => form.reservation_id, () => form.direction], loadSelection)

const edit = async (row) => {
  form.reservation_id = row.reservation_id ?? ''
  form.child_id = row.child_id
  form.direction = row.direction
  loadSelection()
  editor.value.open = true
  await nextTick()
  assigneeInput.value?.focus()
}

const save = () => {
  saved.value = false
  form.put(route('dashboard.transport-assignment'), {
    preserveScroll: true,
    onSuccess: () => { saved.value = true },
  })
}
const refresh = () => router.reload({
  only: ['transportAssignments', 'transportStaff', 'transportVisits', 'transportState', 'localTransportCount'],
  onSuccess: () => { if (editor.value) editor.value.open = false; form.reset(); saved.value = false },
})
let timer
onMounted(() => {
  if (shared.value) timer = setInterval(() => {
    if (document.visibilityState === 'visible' && !editor.value?.open && !form.processing) refresh()
  }, 30000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <section aria-labelledby="staff-transport" class="rounded-xl border bg-white">
    <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
      <h3 id="staff-transport" class="font-semibold text-gray-900">スタッフ全員の送迎</h3>
      <button v-if="shared" type="button" @click="refresh" class="text-sm text-primary-700 underline">最新の担当を確認</button>
    </div>
    <p v-if="syncState === 'connected'" class="px-4 pt-3 text-sm text-gray-600">p-yoyakuと共通の担当です。変更は両方に反映されます。</p>
    <p v-if="syncState === 'unavailable'" role="alert" class="px-4 py-3 text-sm text-red-700">p-yoyakuの送迎情報を取得できません。接続・連携設定とp-yoyakuの更新状況を確認し、再読み込みしてください。</p>
    <p v-if="localCount" class="px-4 pt-3 text-sm text-amber-800">UCRMだけに保存した担当が{{ localCount }}件あります。共通の一覧で担当を選び直してください。</p>
    <p v-if="assignments.length === 0 && syncState !== 'unavailable'" class="px-4 py-4 text-sm text-gray-700">この日の送迎担当は登録されていません。</p>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-gray-700">
          <tr>
            <th scope="col" class="px-4 py-2 whitespace-nowrap">時刻・区分</th>
            <th scope="col" class="px-4 py-2">児童</th>
            <th scope="col" class="px-4 py-2">担当職員</th>
            <th scope="col" class="px-4 py-2">場所</th>
            <th v-if="canManage" scope="col" class="px-4 py-2">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="row in assignments" :key="`${row.child_id}-${row.direction}`">
            <td class="px-4 py-3 whitespace-nowrap">{{ time(row.scheduled_time) || '時刻未登録' }}・{{ directions[row.direction] }}</td>
            <td class="px-4 py-3">{{ visitById.get(row.child_id)?.name }}</td>
            <td class="px-4 py-3 font-medium">{{ row.staff_name || '未割り当て' }}</td>
            <td class="break-words px-4 py-3 text-gray-700">{{ row.location || '場所未登録' }}</td>
            <td v-if="canManage" class="px-4 py-3">
              <button type="button" :disabled="form.processing" @click="edit(row)"
                class="whitespace-nowrap text-primary-700 underline disabled:opacity-50">担当変更</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <details v-if="canManage && syncState !== 'unavailable'" ref="editor" class="border-t">
      <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-primary-700">送迎担当を割り当てる</summary>
      <div class="space-y-4 px-4 pb-4">
        <p v-if="visits.length === 0" class="text-sm text-gray-700">この日の利用児童が表示されると担当を登録できます。</p>
        <template v-else>
          <form class="flex flex-wrap items-end gap-3 text-sm" @submit.prevent="save">
            <label v-if="!shared" class="space-y-1">児童
              <select v-model="form.child_id" required :disabled="form.processing" class="block max-w-full rounded-md border-gray-300 text-sm">
                <option value="" disabled>選択してください</option>
                <option v-for="visit in visits" :key="visit.childId" :value="visit.childId">{{ visit.name }}</option>
              </select>
            </label>
            <label v-else class="space-y-1">児童
              <select v-model="form.reservation_id" required :disabled="form.processing" class="block max-w-full rounded-md border-gray-300 text-sm">
                <option value="" disabled>選択してください</option>
                <option v-for="row in selections" :key="row.reservation_id" :value="row.reservation_id">{{ visitById.get(row.child_id)?.name }}（{{ time(row.scheduled_time) }}）</option>
              </select>
            </label>
            <label class="space-y-1">迎え・送り
              <select v-model="form.direction" :disabled="form.processing" class="block rounded-md border-gray-300 text-sm">
                <option v-for="(label, value) in directions" :key="value" :value="value">{{ label }}</option>
              </select>
            </label>
            <label class="space-y-1">担当職員
              <select ref="assigneeInput" v-model="form.staff_id" :disabled="form.processing" class="block max-w-full rounded-md border-gray-300 text-sm">
                <option :value="null">担当なし</option>
                <option v-if="form.staff_id && !staff.some((person) => person.id === form.staff_id)" :value="form.staff_id" disabled>無効な職員</option>
                <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
              </select>
            </label>
            <label class="space-y-1">時刻
              <input v-model="form.scheduled_time" :disabled="shared" type="time" class="block rounded-md border-gray-300 text-sm" />
            </label>
            <label class="space-y-1">場所
              <input v-model="form.location" :disabled="shared" type="text" maxlength="120" class="block max-w-full rounded-md border-gray-300 text-sm" />
            </label>
            <button type="submit" :disabled="(shared ? !form.reservation_id || !selections.some(row => row.reservation_id === form.reservation_id) : !form.child_id) || form.processing"
              class="rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">保存</button>
          </form>
          <p v-if="shared" class="text-sm text-gray-600">時刻・場所はp-yoyakuの予約内容です。候補にいない職員はp-yoyakuで先生として登録してください。</p>
          <p v-if="saved" role="status" class="text-sm text-green-700">送迎担当を更新しました。</p>
          <ul v-if="form.hasErrors" role="alert" class="text-sm text-red-700">
            <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
          </ul>
        </template>
      </div>
    </details>
  </section>
</template>
