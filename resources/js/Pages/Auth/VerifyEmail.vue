<script setup>
import { computed } from 'vue';
import BreezeButton from '@/Components/Button.vue';
import BreezeGuestLayout from '@/Layouts/Guest.vue';
import { Head, Link, useForm } from '@inertiajs/inertia-vue3';

const props = defineProps({
    status: String,
});

const form = useForm();

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <BreezeGuestLayout>
        <Head title="メール認証" />

        <div class="mb-4 text-sm text-gray-600">
            ご登録ありがとうございます！メールに送信されたリンクをクリックして、メールアドレスを認証してください。メールが届かない場合は、再送いたします。
        </div>

        <div class="mb-4 font-medium text-sm text-green-600" v-if="verificationLinkSent" >
            登録時に入力されたメールアドレスに新しい認証リンクを送信しました。
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <BreezeButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    認証メールを再送信
                </BreezeButton>

                <Link :href="route('logout')" method="post" as="button" class="underline text-sm text-gray-600 hover:text-gray-900">ログアウト</Link>
            </div>
        </form>
    </BreezeGuestLayout>
</template>
