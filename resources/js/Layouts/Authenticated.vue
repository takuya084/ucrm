<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import BreezeDropdown from '@/Components/Dropdown.vue'
import BreezeDropdownLink from '@/Components/DropdownLink.vue'
import WorkspaceNavigation from '@/Components/WorkspaceNavigation.vue'
const menu = ref(null)
const menuTrigger = ref(null)
const restoreScroll = () => { document.body.style.overflow = '' }
const openMenu = () => { menu.value.showModal(); document.body.style.overflow = 'hidden' }
const closeMenu = () => { menu.value?.close(); document.body.style.overflow = ''; menuTrigger.value?.focus() }
onBeforeUnmount(() => { document.body.style.overflow = '' })
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:p-3">本文へ移動</a>
    <aside class="hidden lg:flex fixed inset-y-0 left-0 w-60 bg-white border-r border-gray-200 flex-col">
      <Link :href="route('dashboard')" class="block border-b px-6 py-5 text-xl font-extrabold text-primary-700">ハグくむ</Link>
      <div class="overflow-y-auto flex-1"><WorkspaceNavigation /></div>
    </aside>
    <div class="lg:pl-60">
      <div class="bg-white border-b px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-semibold truncate">{{ $page.props.auth.facility?.name ?? '事業所業務' }}</p>
          <p class="text-xs text-gray-500">{{ $page.props.auth.staff_role === 'admin' ? '管理者' : $page.props.auth.staff_role === 'leader' ? 'リーダー' : '職員' }}</p>
        </div>
        <div class="flex items-center gap-2">
          <BreezeDropdown align="right" width="48">
            <template #trigger><button type="button" class="hidden lg:block px-3 py-2 text-sm rounded-md border border-gray-200">{{ $page.props.auth.user.name }}</button></template>
            <template #content>
              <BreezeDropdownLink :href="route('account.security')">アカウントのセキュリティ（2FA）</BreezeDropdownLink>
              <BreezeDropdownLink :href="route('logout')" method="post" as="button">ログアウト</BreezeDropdownLink>
            </template>
          </BreezeDropdown>
          <button ref="menuTrigger" type="button" @click="openMenu" aria-haspopup="dialog" aria-controls="workspace-menu" class="lg:hidden border border-gray-300 rounded-lg px-4 py-2 text-sm font-semibold">メニュー</button>
        </div>
      </div>
      <header v-if="$slots.header" class="sticky top-0 z-20 bg-white border-b px-4 sm:px-6 py-4"><slot name="header" /></header>
      <main id="main-content" tabindex="-1" class="min-w-0">
        <div v-if="$page.props.errors?.billing" role="alert" class="bg-red-50 border border-red-300 text-red-900 p-4 text-sm whitespace-pre-line">
            {{ $page.props.errors.billing }}
          </div>
        <slot />
      </main>
    </div>
    <dialog ref="menu" id="workspace-menu" aria-labelledby="workspace-menu-title" @cancel.prevent="closeMenu" @click="e => { if (e.target === menu) closeMenu() }" @close="restoreScroll" class="fixed inset-y-0 left-auto right-0 m-0 h-dvh max-h-screen w-80 max-w-[90vw] bg-white p-0 shadow-xl backdrop:bg-black/40">
      <div class="flex items-center justify-between border-b p-4 sticky top-0 bg-white z-10">
        <h2 id="workspace-menu-title" class="font-bold">メニュー</h2>
        <button type="button" @click="closeMenu" class="rounded-md border px-3 py-2 text-sm">閉じる</button>
      </div>
      <WorkspaceNavigation @navigate="closeMenu" />
      <div class="p-4 border-t space-y-3 text-sm">
        <p class="font-semibold">{{ $page.props.auth.user.name }}</p>
        <Link :href="route('account.security')" @click="closeMenu" class="block py-2">アカウントのセキュリティ（2FA）</Link>
        <Link :href="route('logout')" method="post" as="button" @click="closeMenu" class="block py-2 text-red-700">ログアウト</Link>
      </div>
    </dialog>
  </div>
</template>
