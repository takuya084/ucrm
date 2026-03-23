<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  child: Object,
})

const CONTRACT_STATUS = {
  active:    { label: '契約中',   class: 'bg-green-100 text-green-800' },
  suspended: { label: '一時停止', class: 'bg-yellow-100 text-yellow-800' },
  ended:     { label: '契約終了', class: 'bg-gray-100 text-gray-600' },
}

const GENDER = { male: '男', female: '女', other: 'その他' }

const DAY_LABELS = { mon: '月', tue: '火', wed: '水', thu: '木', fri: '金', sat: '土' }

const USAGE_STATUS = {
  attended:      { label: '出席',            class: 'text-green-600' },
  absent:        { label: '無断欠席',        class: 'text-red-600' },
  absent_notice: { label: '欠席（連絡あり）', class: 'text-yellow-600' },
  cancel:        { label: 'キャンセル',       class: 'text-gray-500' },
}

const destroy = () => {
  if (confirm('この児童を削除しますか？（削除後も管理者が復元できます）')) {
    Inertia.delete(route('children.destroy', props.child.id))
  }
}
</script>

<template>
  <Head :title="child.name + ' - 児童詳細'" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="route('children.index')" class="text-gray-400 hover:text-gray-600 text-sm">← 一覧へ</Link>
        <h2 class="font-semibold text-xl text-gray-800">{{ child.name }}</h2>
        <span
          v-if="CONTRACT_STATUS[child.contract_status]"
          :class="['px-2 py-1 rounded-full text-xs font-medium', CONTRACT_STATUS[child.contract_status].class]"
        >{{ CONTRACT_STATUS[child.contract_status].label }}</span>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <FlashMessage />

        <!-- アクションボタン -->
        <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="flex justify-end gap-2">
          <Link :href="route('children.edit', child.id)" class="px-4 py-2 text-sm bg-indigo-500 text-white rounded hover:bg-indigo-600">
            編集
          </Link>
          <button @click="destroy" class="px-4 py-2 text-sm border border-red-300 text-red-500 rounded hover:bg-red-50">
            削除
          </button>
        </div>

        <!-- 基本情報 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">基本情報</h3>
          <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
              <dt class="text-gray-500">児童名カナ</dt>
              <dd class="font-medium mt-1">{{ child.name_kana ?? '―' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">性別</dt>
              <dd class="font-medium mt-1">{{ GENDER[child.gender] ?? '―' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">生年月日</dt>
              <dd class="font-medium mt-1">{{ child.birthdate?.slice(0, 10) ?? '―' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">学年</dt>
              <dd class="font-medium mt-1">{{ child.grade ?? '―' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">学校</dt>
              <dd class="font-medium mt-1">{{ child.school?.name ?? '―' }}</dd>
            </div>
            <div v-if="child.pickup_address" class="col-span-2">
              <dt class="text-gray-500">送迎先住所</dt>
              <dd class="font-medium mt-1">{{ child.pickup_address }}</dd>
            </div>
            <div>
              <dt class="text-gray-500">契約開始日</dt>
              <dd class="font-medium mt-1">{{ child.contract_start_date?.slice(0, 10) ?? '―' }}</dd>
            </div>
          </dl>
        </div>

        <!-- 支援・配慮情報 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">支援・配慮情報</h3>
          <dl class="space-y-3 text-sm">
            <div>
              <dt class="text-gray-500">障がい種別</dt>
              <dd class="mt-1 font-medium">{{ child.disability_type ?? '―' }}</dd>
            </div>
            <div v-if="child.disability_note">
              <dt class="text-gray-500">障がい備考</dt>
              <dd class="mt-1 bg-gray-50 p-2 rounded">{{ child.disability_note }}</dd>
            </div>
            <div v-if="child.allergy_note">
              <dt class="text-gray-500 flex items-center gap-1">
                <span class="text-red-500">⚠</span> アレルギー
              </dt>
              <dd class="mt-1 bg-red-50 p-2 rounded text-red-800">{{ child.allergy_note }}</dd>
            </div>
            <div v-if="child.care_note">
              <dt class="text-gray-500">配慮事項</dt>
              <dd class="mt-1 bg-yellow-50 p-2 rounded">{{ child.care_note }}</dd>
            </div>
          </dl>
        </div>

        <!-- 利用曜日 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-base font-semibold text-gray-800">利用曜日</h3>
            <Link
              v-if="['admin','leader'].includes($page.props.auth.staff_role)"
              :href="route('children.schedules.create', child.id)"
              class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600"
            >＋ 曜日を追加</Link>
          </div>
          <div v-if="child.schedules?.length" class="flex gap-2 flex-wrap">
            <component
              v-for="s in child.schedules"
              :key="s.id"
              :is="['admin','leader'].includes($page.props.auth.staff_role) ? Link : 'span'"
              :href="['admin','leader'].includes($page.props.auth.staff_role) ? route('children.schedules.edit', { child: child.id, schedule: s.id }) : undefined"
              class="flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium"
              :class="['admin','leader'].includes($page.props.auth.staff_role) ? 'hover:bg-indigo-200 transition-colors' : ''"
            >
              {{ DAY_LABELS[s.day_of_week] }}曜日
              <span v-if="s.pickup_required" class="text-xs text-indigo-500">（送迎）</span>
              <span v-if="s.status === 'trial'" class="text-xs bg-yellow-100 text-yellow-600 px-1 rounded">体験</span>
              <span v-if="s.status === 'temporary'" class="text-xs bg-orange-100 text-orange-600 px-1 rounded">一時</span>
            </component>
          </div>
          <p v-else class="text-sm text-gray-400">利用曜日が登録されていません</p>
        </div>

        <!-- 受給者証 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-base font-semibold text-gray-800">受給者証</h3>
            <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="flex gap-2">
              <Link
                v-if="child.active_recipient_certificate"
                :href="route('children.certificates.edit', { child: child.id, certificate: child.active_recipient_certificate.id })"
                class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600"
              >編集</Link>
              <Link
                :href="route('children.certificates.create', child.id)"
                class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600"
              >{{ child.active_recipient_certificate ? '+ 新規登録（更新）' : '+ 受給者証を登録' }}</Link>
            </div>
          </div>
          <div v-if="child.active_recipient_certificate" class="text-sm space-y-2">
            <dl class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div>
                <dt class="text-gray-500">受給者証番号</dt>
                <dd class="font-medium mt-1">{{ child.active_recipient_certificate.certificate_number ?? '―' }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">市区町村</dt>
                <dd class="font-medium mt-1">{{ child.active_recipient_certificate.municipality ?? '―' }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">月あたり支給量</dt>
                <dd class="font-medium mt-1 text-indigo-700 font-bold">{{ child.active_recipient_certificate.monthly_limit }}回/月</dd>
              </div>
              <div>
                <dt class="text-gray-500">有効期間</dt>
                <dd class="font-medium mt-1">
                  {{ child.active_recipient_certificate.valid_from?.slice(0, 10) }} 〜 {{ child.active_recipient_certificate.valid_to?.slice(0, 10) }}
                </dd>
              </div>
              <div v-if="child.active_recipient_certificate.disability_support_category">
                <dt class="text-gray-500">通所支援種別</dt>
                <dd class="font-medium mt-1">{{ child.active_recipient_certificate.disability_support_category }}</dd>
              </div>
            </dl>
          </div>
          <p v-else class="text-sm text-gray-400">受給者証が登録されていません</p>
        </div>

        <!-- 保護者 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">保護者情報</h3>
          <div v-if="child.guardians?.length" class="space-y-3">
            <div
              v-for="g in child.guardians"
              :key="g.id"
              class="flex items-start gap-4 p-3 bg-gray-50 rounded text-sm"
            >
              <div class="flex-1">
                <div class="font-medium text-gray-900">
                  {{ g.name }}
                  <span class="text-xs text-gray-500 ml-2">{{ g.relationship }}</span>
                  <span v-if="g.pivot.is_primary" class="ml-2 px-1 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">主連絡先</span>
                </div>
                <div class="text-gray-500 mt-1">
                  {{ g.tel_primary ?? '電話なし' }}
                  <span v-if="g.email" class="ml-3">{{ g.email }}</span>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">保護者が登録されていません</p>
        </div>

        <!-- 最近の利用記録 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-base font-semibold text-gray-800">最近の利用記録</h3>
          </div>
          <div v-if="child.usage_records?.length" class="space-y-1">
            <div
              v-for="r in child.usage_records"
              :key="r.id"
              class="flex items-center gap-4 text-sm py-2 border-b last:border-0"
            >
              <span class="text-gray-600 w-24 shrink-0">{{ r.date?.slice(0, 10) }}</span>
              <span :class="USAGE_STATUS[r.status]?.class ?? 'text-gray-600'">
                {{ USAGE_STATUS[r.status]?.label ?? r.status }}
              </span>
              <span v-if="r.memo" class="text-gray-400 text-xs">{{ r.memo }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">利用記録がありません</p>
        </div>

        <!-- 個別支援計画 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-base font-semibold text-gray-800">個別支援計画</h3>
            <Link
              v-if="['admin','leader'].includes($page.props.auth.staff_role)"
              :href="route('children.support-plans.create', child.id)"
              class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600"
            >＋ 新規作成</Link>
          </div>
          <div v-if="child.support_plans?.length" class="space-y-2">
            <div
              v-for="plan in child.support_plans"
              :key="plan.id"
              class="flex items-center justify-between p-3 bg-gray-50 rounded text-sm hover:bg-gray-100 transition-colors"
            >
              <div>
                <Link :href="route('children.support-plans.show', [child.id, plan.id])" class="font-medium text-gray-800 hover:text-indigo-600">
                  {{ plan.plan_date?.slice(0, 10) }} 〜 {{ plan.valid_to?.slice(0, 10) ?? '―' }}
                </Link>
                <div class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ plan.short_term_goal || plan.long_term_goal || '目標未記入' }}</div>
              </div>
              <span
                :class="[
                  'text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap',
                  plan.guardian_agreement ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                ]"
              >{{ plan.guardian_agreement ? '同意済' : '同意待ち' }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">個別支援計画が登録されていません</p>
        </div>

        <!-- モニタリング記録 -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-base font-semibold text-gray-800">モニタリング記録</h3>
            <Link
              v-if="['admin','leader'].includes($page.props.auth.staff_role)"
              :href="route('children.monitoring.create', child.id)"
              class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600"
            >＋ 記録する</Link>
          </div>
          <div v-if="child.monitoring_records?.length" class="space-y-2">
            <div
              v-for="mon in child.monitoring_records"
              :key="mon.id"
              class="flex items-center justify-between p-3 bg-gray-50 rounded text-sm hover:bg-gray-100 transition-colors"
            >
              <div>
                <Link :href="route('children.monitoring.show', [child.id, mon.id])" class="font-medium text-gray-800 hover:text-indigo-600">
                  {{ mon.monitoring_date?.slice(0, 10) }}
                </Link>
                <span v-if="mon.period_from" class="text-xs text-gray-500 ml-2">
                  （対象：{{ mon.period_from?.slice(0, 10) }} 〜 {{ mon.period_to?.slice(0, 10) }}）
                </span>
              </div>
              <span v-if="mon.next_review_date" class="text-xs text-gray-500">次回：{{ mon.next_review_date?.slice(0, 10) }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">モニタリング記録がありません</p>
        </div>

        <!-- 問い合わせ一覧リンク -->
        <div class="bg-white shadow-sm rounded-lg p-4 flex justify-between items-center">
          <span class="text-sm font-medium text-gray-700">この児童の問い合わせ</span>
          <Link
            :href="route('inquiries.create', { child_id: child.id })"
            class="text-xs px-3 py-1 bg-indigo-500 text-white rounded hover:bg-indigo-600"
          >＋ 問い合わせ登録</Link>
        </div>

        <!-- メモ -->
        <div v-if="child.memo" class="bg-white shadow-sm rounded-lg p-6">
          <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">メモ</h3>
          <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ child.memo }}</p>
        </div>

      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
