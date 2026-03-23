<script setup>
import { ref } from 'vue'

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
                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none transition duration-150 ease-in-out"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                  <path :class="{'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown}" class="sm:hidden">
          <div class="pt-2 pb-3 space-y-1">
            <BreezeResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
              ダッシュボード
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink :href="route('usage-records.index')" :active="route().current('usage-records.index')">
              出席管理
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink :href="route('vacancy-adjustment.index')" :active="route().current('vacancy-adjustment.index')" class="pl-10">
              └ 空き枠調整
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink :href="route('program-progress.index')" :active="route().current('program-progress.*')">
              療育進度
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink :href="route('inquiries.index')" :active="route().current('inquiries.*')">
              問い合わせ
            </BreezeResponsiveNavLink>
          </div>

          <!-- Responsive Settings -->
          <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
              <div class="font-medium text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
              <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
            </div>
            <div class="mt-3 space-y-1">
              <BreezeResponsiveNavLink v-if="['admin','leader'].includes($page.props.auth.staff_role)" :href="route('schools.index')" :active="route().current('schools.*')">
                学校マスタ
              </BreezeResponsiveNavLink>
              <BreezeResponsiveNavLink :href="route('programs.index')" :active="route().current('programs.*')">
                プログラムマスタ
              </BreezeResponsiveNavLink>
              <BreezeResponsiveNavLink :href="route('children.index')" :active="route().current('children.*')">
                児童管理
              </BreezeResponsiveNavLink>
              <BreezeResponsiveNavLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('staff.index')" :active="route().current('staff.*')">
                職員管理
              </BreezeResponsiveNavLink>
              <BreezeResponsiveNavLink v-if="$page.props.auth.staff_role === 'admin'" :href="route('facility.edit')" :active="route().current('facility.*')">
                施設設定
              </BreezeResponsiveNavLink>
              <BreezeResponsiveNavLink :href="route('logout')" method="post" as="button"
                class="block w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-sm font-medium text-red-600 hover:bg-red-50 hover:border-red-300 focus:outline-none transition duration-150 ease-in-out">
                ログアウト
              </BreezeResponsiveNavLink>
            </div>
          </div>
        </div>
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
