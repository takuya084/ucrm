<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/inertia-vue3'
import BreezeValidationErrors from '@/Components/ValidationErrors.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import { reactive } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  facility: Object,
})

const form = reactive({
  name:               props.facility.name ?? '',
  address:            props.facility.address ?? '',
  tel:                props.facility.tel ?? '',
  fax:                props.facility.fax ?? '',
  capacity_per_day:   props.facility.capacity_per_day ?? '',
  yoyaku_business_id:     props.facility.yoyaku_business_id ?? '',
  yoyaku_api_token:       props.facility.yoyaku_api_token ?? '',
  yoyaku_webhook_secret:  props.facility.yoyaku_webhook_secret ?? '',
  facility_code:      props.facility.facility_code ?? '',
  service_type:       props.facility.service_type ?? 'houday',
  area_unit_price:    props.facility.area_unit_price ?? '10.00',
  designated_date:    props.facility.designated_date ?? '',
  administrator_name: props.facility.administrator_name ?? '',
})

const update = () => {
  Inertia.patch(route('facility.update'), form)
}

const inputClass = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300'
const labelClass = 'block text-sm font-medium text-gray-700 mb-1'
</script>

<template>
  <Head title="施設設定" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800">施設設定</h2>
    </template>

    <div class="py-8">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <FlashMessage />
        <BreezeValidationErrors />

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <form @submit.prevent="update" class="space-y-6">

            <!-- 基本情報 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">基本情報</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">施設名 <span class="text-red-500">*</span></label>
                  <input v-model="form.name" type="text" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">住所</label>
                  <input v-model="form.address" type="text" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">電話番号</label>
                  <input v-model="form.tel" type="text" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">FAX番号</label>
                  <input v-model="form.fax" type="text" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">1日の定員</label>
                  <input v-model.number="form.capacity_per_day" type="number" min="1" max="100" :class="inputClass" />
                </div>
              </div>
            </section>

            <!-- 請求設定 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">請求設定</h3>
              <div class="space-y-4">
                <div>
                  <label :class="labelClass">事業所番号（10桁）</label>
                  <input v-model="form.facility_code" type="text" maxlength="10" :class="inputClass" placeholder="例: 1350000001" />
                  <p class="text-xs text-gray-400 mt-1">国保連請求に使用する事業所番号を入力してください。</p>
                </div>
                <div>
                  <label :class="labelClass">サービス種別</label>
                  <select v-model="form.service_type" :class="inputClass">
                    <option value="houday">放課後等デイサービス</option>
                    <option value="jidou">児童発達支援</option>
                    <option value="both">両方（多機能型）</option>
                  </select>
                </div>
                <div>
                  <label :class="labelClass">地域区分単価（円）</label>
                  <input v-model="form.area_unit_price" type="number" step="0.01" min="0" max="20" :class="inputClass" />
                  <p class="text-xs text-gray-400 mt-1">地域区分に応じた1単位あたりの単価。例: 1級地=11.40、6級地=10.00</p>
                </div>
                <div>
                  <label :class="labelClass">管理者氏名</label>
                  <input v-model="form.administrator_name" type="text" :class="inputClass" />
                </div>
                <div>
                  <label :class="labelClass">指定日</label>
                  <input v-model="form.designated_date" type="date" :class="inputClass" />
                </div>
              </div>
            </section>

            <!-- houkago-plus 連携設定 -->
            <section>
              <h3 class="text-base font-semibold text-gray-800 border-b pb-2 mb-4">
                送迎予約システム連携
              </h3>
              <div class="space-y-3">
                <div>
                  <label :class="labelClass">houkago-plus 事業所ID</label>
                  <input
                    v-model.number="form.yoyaku_business_id"
                    type="number"
                    min="1"
                    :class="inputClass"
                    placeholder="未入力の場合は固定スケジュールで動作"
                  />
                  <p class="text-xs text-gray-400 mt-1">
                    houkago-plus の管理者画面で確認できる事業所IDを入力してください。
                    未入力の場合、出席管理は登録された固定曜日スケジュールを使用します。
                  </p>
                </div>

                <div>
                  <label :class="labelClass">API トークン</label>
                  <input v-model="form.yoyaku_api_token" type="text" :class="inputClass"
                    placeholder="houkago-plus のマスター管理者が発行した Bearer トークン" />
                  <p class="text-xs text-gray-400 mt-1">未入力の場合は .env の HOUKAGO_PLUS_API_TOKEN を利用します。</p>
                </div>

                <div>
                  <label :class="labelClass">Webhook 共通シークレット</label>
                  <input v-model="form.yoyaku_webhook_secret" type="text" :class="inputClass"
                    placeholder="任意の英数字文字列（HMAC署名検証用）" />
                  <p class="text-xs text-gray-400 mt-1">
                    p-yoyaku 側の Business.webhook_secret と同じ値を設定してください。
                    受信URL: <code class="bg-gray-100 px-1">/api/webhooks/yoyaku</code>
                  </p>
                </div>

                <!-- 連携状態インジケーター -->
                <div v-if="form.yoyaku_business_id"
                  class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700">
                  <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                  houkago-plus 事業所ID {{ form.yoyaku_business_id }} と連携します
                </div>
                <div v-else
                  class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded text-sm text-gray-500">
                  <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                  未連携（固定スケジュールで動作）
                </div>
              </div>
            </section>

            <div class="flex justify-end pt-4 border-t">
              <button
                type="submit"
                class="px-6 py-2 text-sm text-white bg-indigo-500 rounded hover:bg-indigo-600"
              >
                保存する
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
