<script setup>
import { ref, watch, nextTick } from 'vue'

import BreezeDropdown from '@/Components/Dropdown.vue'
import BreezeDropdownLink from '@/Components/DropdownLink.vue'
import BreezeNavLink from '@/Components/NavLink.vue'
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link } from '@inertiajs/inertia-vue3'

const showingNavigationDropdown = ref(false)
const showingAttendanceDropdown = ref(false)

const attendanceActive = () =>
  route().current('usage-records.index') ||
  route().current('vacancy-adjustment.index')

// メニュー開閉時にbodyスクロールを制御
watch(showingNavigationDropdown, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
  <div>
    <div class="min-h-screen bg-gray-100">
      <nav class="bg-white border-b border-gray-100">
        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between h-16">
            <div class="flex">
              <!-- Title -->
              <div class="shrink-0 flex items-center">
                <Link :href="route('dashboard')" class="text-lg font-extrabold tracking-tight whitespace-nowrap">
                  <span class="bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">ハグ</span><span class="text-gray-900">くむ</span>
                </Link>
              </div>

              <!-- Navigation Links -->
              <div class="hidden space-x-1 sm:-my-px sm:ml-8 sm:flex items-center">
                <BreezeNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                  ダッシュボード
                </BreezeNavLink>

                <!-- 利用管理ドロップダウン -->
                <div class="relative flex items-center h-full">
                  <BreezeDropdown align="left" width="48" @click.stop>
                    <template #trigger>
                      <button
                        type="button"
                        :class="[
                          'inline-flex items-center gap-1 px-3 py-2 text-sm font-medium border-b-2 transition duration-150 ease-in-out focus:outline-none',
                          attendanceActive()
                            ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300'
                        ]"
                      >
                        利用管理
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </template>
                    <template #content>
                      <BreezeDropdownLink :href="route('usage-records.index')">
                        出席管理
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('vacancy-adjustment.index')">
                        空き枠調整
                      </BreezeDropdownLink>
                    </template>
                  </BreezeDropdown>
                </div>

                <BreezeNavLink :href="route('program-progress.index')" :active="route().current('program-progress.*')">
                  療育進度
                </BreezeNavLink>
                <BreezeNavLink :href="route('inquiries.index')" :active="route().current('inquiries.*')">
                  問い合わせ
                </BreezeNavLink>
              </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ml-6 gap-2">
              <!-- User Dropdown -->
              <BreezeDropdown align="right" width="48">
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                      {{ $page.props.auth.user.name }}
                      <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </span>
                </template>
                <template #content>
                  <BreezeDropdownLink v-if="['admin','leader'].includes($page.props.auth.staff_role)" :href="route('schools.index')">
                    学校マスタ
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('programs.index')">
                    プログラムマスタ
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('children.index')">
                    児童管理
                  </BreezeDropdownLink>
                  <BreezeDropdownLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('staff.index')">
                    職員管理
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('shifts.index')">
                    シフト管理
                  </BreezeDropdownLink>
                  <BreezeDropdownLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('facility.edit')">
                    施設設定
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('logout')" method="post" as="button"
                    class="block w-full px-4 py-2 text-left text-sm leading-5 text-red-600 hover:bg-red-50 focus:outline-none focus:bg-red-50 transition duration-150 ease-in-out">
                    ログアウト
                  </BreezeDropdownLink>
                </template>
              </BreezeDropdown>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                class="relative w-10 h-10 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 active:bg-gray-200 focus:outline-none transition"
                aria-label="メニュー"
              >
                <span class="sr-only">メニュー</span>
                <span class="block w-5 h-5 relative">
                  <span :class="[
                    'absolute left-0 w-full h-0.5 bg-current rounded transition-all duration-300',
                    showingNavigationDropdown ? 'top-[9px] rotate-45' : 'top-0.5'
                  ]" />
                  <span :class="[
                    'absolute left-0 top-[9px] w-full h-0.5 bg-current rounded transition-all duration-300',
                    showingNavigationDropdown ? 'opacity-0 scale-x-0' : 'opacity-100 scale-x-100'
                  ]" />
                  <span :class="[
                    'absolute left-0 w-full h-0.5 bg-current rounded transition-all duration-300',
                    showingNavigationDropdown ? 'top-[9px] -rotate-45' : 'top-[17px]'
                  ]" />
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- Mobile Overlay Menu -->
        <Transition
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div v-if="showingNavigationDropdown" class="sm:hidden fixed inset-0 z-50">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/20 backdrop-blur-sm" @click="showingNavigationDropdown = false" />

            <!-- Slide-in Panel -->
            <Transition
              enter-active-class="transition duration-300 ease-out"
              enter-from-class="translate-x-full"
              enter-to-class="translate-x-0"
              leave-active-class="transition duration-200 ease-in"
              leave-from-class="translate-x-0"
              leave-to-class="translate-x-full"
              appear
            >
              <div v-if="showingNavigationDropdown"
                class="absolute right-0 top-0 bottom-0 w-72 bg-white shadow-xl flex flex-col">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b">
                  <div>
                    <div class="font-semibold text-gray-900">{{ $page.props.auth.user.name }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $page.props.auth.user.email }}</div>
                  </div>
                  <button @click="showingNavigationDropdown = false"
                    class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 active:bg-gray-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>

                <!-- Menu Items -->
                <div class="flex-1 overflow-y-auto py-3">
                  <!-- メイン -->
                  <div class="px-4 mb-2">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">メニュー</div>
                  </div>
                  <Link :href="route('dashboard')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('dashboard') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/></svg>
                    ダッシュボード
                  </Link>
                  <Link :href="route('usage-records.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('usage-records.index') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    出席管理
                  </Link>
                  <Link :href="route('vacancy-adjustment.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item pl-12', route().current('vacancy-adjustment.index') && 'mobile-nav-active']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    空き枠調整
                  </Link>
                  <Link :href="route('program-progress.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('program-progress.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    療育進度
                  </Link>
                  <Link :href="route('inquiries.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('inquiries.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    問い合わせ
                  </Link>

                  <!-- 設定 -->
                  <div class="px-4 mt-5 mb-2">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">設定</div>
                  </div>
                  <Link v-if="['admin','leader'].includes($page.props.auth.staff_role)"
                    :href="route('schools.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('schools.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M12 14l9-5-9-5-9 5 9 5zm0 7l-9-5 9 5 9-5-9 5zm0-7v7"/></svg>
                    学校マスタ
                  </Link>
                  <Link :href="route('programs.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('programs.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    プログラムマスタ
                  </Link>
                  <Link :href="route('children.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('children.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    児童管理
                  </Link>
                  <Link v-if="$page.props.auth.staff_role === 'admin'"
                    :href="route('staff.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('staff.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                    職員管理
                  </Link>
                  <Link :href="route('shifts.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('shifts.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    シフト管理
                  </Link>
                  <Link v-if="$page.props.auth.staff_role === 'admin'"
                    :href="route('facility.edit')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('facility.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    施設設定
                  </Link>
                </div>

                <!-- Footer: Logout -->
                <div class="border-t px-4 py-3">
                  <Link :href="route('logout')" method="post" as="button"
                    class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50 active:bg-red-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    ログアウト
                  </Link>
                </div>
              </div>
            </Transition>
          </div>
        </Transition>
      </nav>

      <!-- Page Heading -->
      <header class="bg-white shadow" v-if="$slots.header">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main>
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.mobile-nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 1rem 0.625rem 1.25rem;
  margin: 0.125rem 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  border-radius: 0.5rem;
  transition: background-color 150ms, color 150ms;
}
.mobile-nav-item:hover {
  background-color: #f3f4f6;
}
.mobile-nav-item:active {
  background-color: #e5e7eb;
}
.mobile-nav-active {
  background-color: #eef2ff;
  color: #4338ca;
}
</style>
