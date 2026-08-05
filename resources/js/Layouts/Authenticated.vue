<script setup>
import { ref, watch, nextTick } from 'vue'

import BreezeDropdown from '@/Components/Dropdown.vue'
import BreezeDropdownLink from '@/Components/DropdownLink.vue'
import BreezeNavLink from '@/Components/NavLink.vue'
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link } from '@inertiajs/vue3'

const showingNavigationDropdown = ref(false)
const showingAttendanceDropdown = ref(false)

const attendanceActive = () =>
  route().current('usage-records.index') ||
  route().current('contact-notes.index') ||
  route().current('vacancy-adjustment.index')

const billingActive = () =>
  route().current('billing.*')

// メニュー開閉時にbodyスクロールを制御
watch(showingNavigationDropdown, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
  <div>
    <div class="min-h-screen bg-gray-50">
      <nav class="bg-white border-b border-gray-200">
        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between h-16">
            <div class="flex">
              <!-- Title -->
              <div class="shrink-0 flex items-center">
                <Link :href="route('dashboard')" class="text-lg font-extrabold tracking-tight whitespace-nowrap">
                  <span class="text-primary-600">ハグ</span><span class="text-gray-900">くむ</span>
                </Link>
              </div>

              <!-- Navigation Links -->
              <div class="hidden space-x-1 sm:-my-px sm:ml-8 sm:flex items-center">
                <BreezeNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                  ダッシュボード
                </BreezeNavLink>

                <BreezeNavLink :href="route('children.index')" :active="route().current('children.*')">
                  児童
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
                            ? 'border-primary-400 text-gray-900 focus:border-primary-700'
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
                      <BreezeDropdownLink :href="route('contact-notes.index')">
                        連絡帳
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('vacancy-adjustment.index')">
                        空き枠調整
                      </BreezeDropdownLink>
                    </template>
                  </BreezeDropdown>
                </div>

                <!-- 請求管理ドロップダウン（leader以上） -->
                <div v-if="['admin','leader'].includes($page.props.auth.staff_role)" class="relative flex items-center h-full">
                  <BreezeDropdown align="left" width="48" @click.stop>
                    <template #trigger>
                      <button
                        type="button"
                        :class="[
                          'inline-flex items-center gap-1 px-3 py-2 text-sm font-medium border-b-2 transition duration-150 ease-in-out focus:outline-none',
                          billingActive()
                            ? 'border-primary-400 text-gray-900 focus:border-primary-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300'
                        ]"
                      >
                        請求管理
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </template>
                    <template #content>
                      <div class="px-4 pt-2 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">月次業務</div>
                      <BreezeDropdownLink :href="route('billing.previous-month')" class="text-primary-600 font-medium">
                        前月請求詳細（月初確認）
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.index')">
                        月次請求
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.daily-records.index')">
                        実績記録票
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.cap-management.index')">
                        上限管理
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.invoices.index')">
                        利用者請求
                      </BreezeDropdownLink>
                      <div class="border-t my-1" />
                      <div class="px-4 pt-1 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">例外対応</div>
                      <BreezeDropdownLink :href="route('billing.error-claims.index')">
                        過誤申立
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.returns.index')">
                        返戻管理
                      </BreezeDropdownLink>
                      <div class="border-t my-1" />
                      <div class="px-4 pt-1 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">設定</div>
                      <BreezeDropdownLink :href="route('billing.expenses.index')">
                        経費管理
                      </BreezeDropdownLink>
                      <BreezeDropdownLink :href="route('billing.settings.service-codes')">
                        加算・減算設定
                      </BreezeDropdownLink>
                    </template>
                  </BreezeDropdown>
                </div>

                <BreezeNavLink :href="route('program-progress.index')" :active="route().current('program-progress.*')">
                  療育進度
                </BreezeNavLink>
                <BreezeNavLink :href="route('shifts.index')" :active="route().current('shifts.*')">
                  シフト
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
                  <BreezeDropdownLink v-if="['admin','leader'].includes($page.props.auth.staff_role)" :href="route('external-facilities.index')">
                    他社事業所マスタ
                  </BreezeDropdownLink>
                  <BreezeDropdownLink v-if="['admin','leader'].includes($page.props.auth.staff_role)" :href="route('operation-records.index')">
                    運営記録
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('programs.index')">
                    プログラムマスタ
                  </BreezeDropdownLink>
                  <BreezeDropdownLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('staff.index')">
                    職員管理
                  </BreezeDropdownLink>
                  <BreezeDropdownLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('facility.edit')">
                    施設設定
                  </BreezeDropdownLink>
                  <BreezeDropdownLink :href="route('account.security')">
                    アカウントのセキュリティ（2FA）
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
                    'absolute left-0 w-full h-0.5 bg-current rounded-md transition-all duration-300',
                    showingNavigationDropdown ? 'top-[9px] rotate-45' : 'top-0.5'
                  ]" />
                  <span :class="[
                    'absolute left-0 top-[9px] w-full h-0.5 bg-current rounded-md transition-all duration-300',
                    showingNavigationDropdown ? 'opacity-0 scale-x-0' : 'opacity-100 scale-x-100'
                  ]" />
                  <span :class="[
                    'absolute left-0 w-full h-0.5 bg-current rounded-md transition-all duration-300',
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
                  <Link :href="route('children.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('children.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    児童管理
                  </Link>
                  <Link :href="route('usage-records.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('usage-records.index') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    出席管理
                  </Link>
                  <Link :href="route('contact-notes.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item pl-12', route().current('contact-notes.index') && 'mobile-nav-active']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    連絡帳
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
                  <Link :href="route('shifts.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('shifts.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    シフト管理
                  </Link>
                  <Link :href="route('inquiries.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('inquiries.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    問い合わせ
                  </Link>

                  <!-- 請求管理（leader以上） -->
                  <template v-if="['admin','leader'].includes($page.props.auth.staff_role)">
                    <!-- 月次業務 -->
                    <div class="px-4 mt-5 mb-2">
                      <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">月次業務</div>
                    </div>
                    <Link :href="route('billing.previous-month')" @click="showingNavigationDropdown = false"
                      class="mobile-nav-item text-primary-600 font-medium">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M15 19l-7-7 7-7"/></svg>
                      前月請求詳細（月初確認）
                    </Link>
                    <Link :href="route('billing.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.index') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                      月次請求
                    </Link>
                    <Link :href="route('billing.daily-records.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.daily-records.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                      実績記録票
                    </Link>
                    <Link :href="route('billing.cap-management.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.cap-management.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      上限管理
                    </Link>
                    <Link :href="route('billing.invoices.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.invoices.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                      利用者請求
                    </Link>

                    <!-- 例外対応 -->
                    <div class="px-4 mt-4 mb-2">
                      <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">例外対応</div>
                    </div>
                    <Link :href="route('billing.error-claims.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.error-claims.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      過誤申立
                    </Link>
                    <Link :href="route('billing.returns.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.returns.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                      返戻管理
                    </Link>

                    <!-- 設定 -->
                    <div class="px-4 mt-4 mb-2">
                      <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">請求・経費 設定</div>
                    </div>
                    <Link :href="route('billing.expenses.index')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.expenses.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                      経費管理
                    </Link>
                    <Link :href="route('billing.settings.service-codes')" @click="showingNavigationDropdown = false"
                      :class="['mobile-nav-item', route().current('billing.settings.*') && 'mobile-nav-active']">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      加算・減算設定
                    </Link>
                  </template>

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
                  <Link v-if="['admin','leader'].includes($page.props.auth.staff_role)"
                    :href="route('external-facilities.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('external-facilities.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    他社事業所マスタ
                  </Link>
                  <Link v-if="['admin','leader'].includes($page.props.auth.staff_role)"
                    :href="route('operation-records.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('operation-records.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M9 12h6m-6 4h6M9 8h6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    運営記録
                  </Link>
                  <Link :href="route('programs.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('programs.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    プログラムマスタ
                  </Link>
                  <Link v-if="$page.props.auth.staff_role === 'admin'"
                    :href="route('staff.index')" @click="showingNavigationDropdown = false"
                    :class="['mobile-nav-item', route().current('staff.*') && 'mobile-nav-active']">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                    職員管理
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
      <header class="bg-white border-b border-gray-200" v-if="$slots.header">
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
  background-color: #f0f7ff;
  color: #0052a3;
}
</style>
