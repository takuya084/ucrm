<script setup>
import { ref, nextTick } from 'vue';
import BreezeButton from '@/Components/Button.vue';
import BreezeGuestLayout from '@/Layouts/Guest.vue';
import BreezeInput from '@/Components/Input.vue';
import BreezeLabel from '@/Components/Label.vue';
import BreezeValidationErrors from '@/Components/ValidationErrors.vue';
import { Head, useForm } from '@inertiajs/vue3';

const usingRecoveryCode = ref(false);
const codeInput = ref(null);
const recoveryCodeInput = ref(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleRecoveryCode = async () => {
    usingRecoveryCode.value = !usingRecoveryCode.value;
    form.reset();
    await nextTick();
    (usingRecoveryCode.value ? recoveryCodeInput : codeInput).value?.focus();
};

const submit = () => {
    form.post('/two-factor-challenge', {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <BreezeGuestLayout>
        <Head title="二要素認証" />

        <div class="mb-4 text-sm text-gray-600">
            <template v-if="!usingRecoveryCode">
                認証アプリ（Google Authenticator 等）に表示されている6桁のコードを入力してください。
            </template>
            <template v-else>
                2FA 設定時に保存したリカバリコードのいずれか1つを入力してください。
            </template>
        </div>

        <BreezeValidationErrors class="mb-4" />

        <form @submit.prevent="submit">
            <div v-if="!usingRecoveryCode">
                <BreezeLabel for="code" value="認証コード" />
                <BreezeInput
                    id="code"
                    ref="codeInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                    v-model="form.code"
                    autofocus
                />
            </div>

            <div v-else>
                <BreezeLabel for="recovery_code" value="リカバリコード" />
                <BreezeInput
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    type="text"
                    autocomplete="off"
                    class="mt-1 block w-full"
                    v-model="form.recovery_code"
                />
            </div>

            <div class="flex items-center justify-between mt-4">
                <button
                    type="button"
                    class="underline text-sm text-gray-600 hover:text-gray-900"
                    @click="toggleRecoveryCode"
                >
                    {{ usingRecoveryCode ? '認証コードを使う' : 'リカバリコードを使う' }}
                </button>

                <BreezeButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    ログイン
                </BreezeButton>
            </div>
        </form>
    </BreezeGuestLayout>
</template>
