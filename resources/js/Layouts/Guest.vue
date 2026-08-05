<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <Link href="/" class="text-3xl font-extrabold tracking-tight">
                <span class="text-primary-600">ハグ</span><span class="text-gray-900">くむ</span>
            </Link>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white border border-gray-200 shadow-sm overflow-hidden sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
