<script setup>
import Authenticated from '@/Layouts/Authenticated.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
  date: String, year: Number, categories: Object,
  activities: Array, annualEvents: Array, programs: Array, staff: Array,
})

const selectedDate = ref(props.date)
const editing = ref(null)
const form = useForm({
  activity_date: props.date, category: 'support', program_id: null,
  staff_id: null, title: '', starts_at: '', materials: '',
})

const reset = () => {
  editing.value = null
  form.reset()
  form.activity_date = props.date
  form.clearErrors()
}

watch(() => props.date, (date) => {
  selectedDate.value = date
  reset()
})

const edit = (activity) => {
  editing.value = activity
  form.clearErrors()
  for (const field of Object.keys(form.data())) form[field] = activity[field] ?? null
  form.starts_at = activity.starts_at?.slice(0, 5) || ''
}

const save = () => {
  const options = { preserveScroll: true, onSuccess: reset }
  if (editing.value) form.patch(route('daily-activities.update', editing.value.id), options)
  else form.post(route('daily-activities.store'), options)
}

const remove = (activity) => {
  if (window.confirm(`「${activity.title}」の予定を削除しますか？`)) {
    router.delete(route('daily-activities.destroy', activity.id), { preserveScroll: true, onSuccess: reset })
  }
}
</script>

<template>
  <Head title="療育予定・年間行事" />
  <Authenticated>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">療育予定・年間行事</h2>
        <Link :href="route('dashboard', { date }) + '#activity-preparation'" class="text-sm text-primary-700 underline">日別の業務へ戻る</Link>
      </div>
    </template>

    <div class="mx-auto max-w-5xl space-y-5 px-4 py-6">
      <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm font-medium">表示日
          <input v-model="selectedDate" type="date" class="mt-1 block rounded-md border-gray-300"
            @change="selectedDate && router.get(route('daily-activities.index'), { date: selectedDate })" />
        </label>
        <Link :href="route('programs.index')" class="py-2 text-sm text-primary-700 underline">登録済みプログラムを見る</Link>
      </div>

      <section class="rounded-xl border bg-white p-4" aria-labelledby="planned-activities">
        <h3 id="planned-activities" class="font-semibold">{{ date }} の予定</h3>
        <p v-if="!activities.length" class="mt-3 text-sm text-gray-600">予定は登録されていません。</p>
        <ul v-else class="mt-2 divide-y">
          <li v-for="activity in activities" :key="activity.id" class="flex flex-wrap justify-between gap-3 py-3 text-sm">
            <div class="min-w-0">
              <p class="break-words font-semibold">{{ categories[activity.category] }} ／ {{ activity.title }}</p>
              <p class="mt-1 text-gray-700">{{ activity.starts_at?.slice(0, 5) || '時刻未定' }} ／ {{ activity.staff?.name || '担当未定' }}</p>
              <p v-if="activity.materials" class="mt-1 whitespace-pre-wrap break-words text-gray-700">準備物　{{ activity.materials }}</p>
            </div>
            <div class="flex gap-3">
              <a href="#activity-form" @click="edit(activity)" class="text-primary-700 underline">編集</a>
              <button type="button" @click="remove(activity)" class="text-red-700 underline">削除</button>
            </div>
          </li>
        </ul>
      </section>

      <section id="activity-form" class="scroll-mt-4 rounded-xl border bg-white p-4" aria-labelledby="activity-form-title">
        <h3 id="activity-form-title" class="font-semibold">{{ editing ? '予定を編集' : '予定を追加' }}</h3>
        <form class="mt-3 space-y-3" @submit.prevent="save">
          <div class="grid gap-3 text-sm sm:grid-cols-2">
            <label>実施日<input v-model="form.activity_date" required type="date" class="mt-1 block w-full rounded-md border-gray-300" /></label>
            <label>種類
              <select v-model="form.category" class="mt-1 block w-full rounded-md border-gray-300">
                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
              </select>
            </label>
            <label>登録済みプログラム
              <select v-model="form.program_id" class="mt-1 block w-full rounded-md border-gray-300">
                <option :value="null">選択しない</option>
                <option v-if="editing?.program_id && !programs.some((p) => p.id === editing.program_id)" :value="editing.program_id">登録済み（利用停止中）</option>
                <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
              </select>
            </label>
            <label>予定名
              <input v-model="form.title" :required="!form.program_id" maxlength="255"
                placeholder="プログラム選択時は省略可" class="mt-1 block w-full rounded-md border-gray-300" />
            </label>
            <label>開始時刻<input v-model="form.starts_at" type="time" class="mt-1 block w-full rounded-md border-gray-300" /></label>
            <label>担当者
              <select v-model="form.staff_id" class="mt-1 block w-full rounded-md border-gray-300">
                <option :value="null">未定</option>
                <option v-if="editing?.staff_id && !staff.some((person) => person.id === editing.staff_id)" :value="editing.staff_id">{{ editing.staff?.name }}（無効）</option>
                <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
              </select>
            </label>
          </div>
          <label class="block text-sm">準備物・準備メモ
            <textarea v-model="form.materials" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-gray-300" />
          </label>
          <ul v-if="form.hasErrors" class="text-sm text-red-700" role="alert">
            <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
          </ul>
          <button type="submit" :disabled="form.processing" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">保存</button>
          <button v-if="editing" type="button" @click="reset" class="ml-3 text-sm text-gray-700 underline">編集をやめる</button>
        </form>
      </section>

      <section class="rounded-xl border bg-white p-4" aria-labelledby="annual-events">
        <h3 id="annual-events" class="font-semibold">{{ year }}年の行事</h3>
        <p v-if="!annualEvents.length" class="mt-3 text-sm text-gray-600">行事は登録されていません。</p>
        <ul v-else class="mt-2 divide-y">
          <li v-for="event in annualEvents" :key="event.id" class="flex flex-wrap justify-between gap-3 py-3 text-sm">
            <Link :href="route('daily-activities.index', { date: event.activity_date })" class="font-medium text-primary-700 underline">
              {{ event.activity_date }} ／ {{ event.title }}
            </Link>
            <span>{{ event.staff?.name || '担当未定' }}</span>
          </li>
        </ul>
      </section>
    </div>
  </Authenticated>
</template>
