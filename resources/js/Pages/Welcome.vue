<script setup>
import { Head, Link } from '@inertiajs/inertia-vue3'
import { ref } from 'vue'

const props = defineProps({
  canLogin:        Boolean,
  canRegister:     Boolean,
  stripePublicKey: String,
})

const isProcessing = ref(false)

const startCheckout = async () => {
  if (isProcessing.value) return
  isProcessing.value = true

  try {
    const res = await fetch('/create-checkout-session', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      },
    })
    const data = await res.json()

    const stripe = window.Stripe(props.stripePublicKey)
    await stripe.redirectToCheckout({ sessionId: data.id })
  } catch (e) {
    alert('決済の開始に失敗しました。もう一度お試しください。')
    isProcessing.value = false
  }
}

const features = [
  {
    image: '/images/feature-attendance.png',
    title: '出席・送迎管理',
    desc: '毎日の出席状況をワンクリックで記録。送迎の迎え・送り完了も一画面で管理できます。',
  },
  {
    image: '/images/feature-children.png',
    title: '児童管理',
    desc: '受給者証の期限管理、固定スケジュール、保護者情報をまとめて管理。期限アラートで更新漏れを防ぎます。',
  },
  {
    image: '/images/feature-plan.png',
    title: '個別支援計画',
    desc: '前回の計画と日々の支援記録をもとに、AIが次回計画の下書きを自動生成。編集して保存するだけで完成です。',
  },
  {
    image: '/images/feature-monitoring.png',
    title: 'モニタリング記録',
    desc: '支援期間中の経過をAIがまとめ、強み・課題・保護者ニーズを自動下書き。記録の負担を大幅に削減します。',
  },
  {
    image: '/images/feature-grid.png',
    title: '療育進度グリッド',
    desc: 'プログラム項目ごとの習熟度を一覧で把握。「今日の出席児童のみ表示」でその日の支援に集中できます。',
  },
  {
    image: '/images/feature-schedule.png',
    title: '空き枠調整',
    desc: '曜日ごとの利用スケジュールを視覚的に確認。欠席が出たときの補充候補もすぐに見つかります。',
  },
  {
    image: '/images/feature-shuttle.png',
    title: '送迎予約システム連携',
    desc: '別途ご利用の送迎予約システム（houkago-plus）と連携し、当日の送迎時刻・場所を自動取り込みできます。',
  },
  {
    image: '/images/feature-inquiry.png',
    title: '問い合わせ管理',
    desc: '見学・体験のお問い合わせを一元管理。対応状況をステータスで追跡し、入力漏れを防ぎます。',
  },
]

const faqs = [
  {
    q: '導入にどのくらい時間がかかりますか？',
    a: '最短10分で初期設定が完了します。児童情報のCSVインポートにも対応しており、既存データの移行もスムーズです。',
  },
  {
    q: 'スマートフォンから使えますか？',
    a: 'はい。レスポンシブ対応しているため、スマートフォンやタブレットからもご利用いただけます。',
  },
  {
    q: 'AI機能の利用に追加料金はかかりますか？',
    a: 'いいえ。月額料金にAI機能（個別支援計画・モニタリング記録の自動下書き）が含まれています。',
  },
  {
    q: '複数事業所での利用は可能ですか？',
    a: 'はい。複数事業所の管理に対応しています。詳しくはお問い合わせください。',
  },
]
</script>

<template>
  <Head title="ハグくむ | 放課後等デイサービス専用クラウド管理システム" />

  <div class="min-h-screen bg-white text-gray-800 font-sans antialiased">

    <!-- ナビゲーション -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
      <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xl font-extrabold tracking-tight">
            <span class="bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">ハグ</span><span class="text-gray-900">くむ</span>
          </span>
          <span class="text-[10px] bg-gradient-to-r from-indigo-500 to-purple-500 text-white px-2 py-0.5 rounded-full font-bold tracking-wide">BETA</span>
        </div>
        <nav class="hidden sm:flex items-center gap-8 text-sm text-gray-500">
          <a href="#features" class="hover:text-gray-900 transition">機能</a>
          <a href="#pricing" class="hover:text-gray-900 transition">料金</a>
          <a href="#faq" class="hover:text-gray-900 transition">FAQ</a>
        </nav>
        <div class="flex items-center gap-3">
          <template v-if="canLogin">
            <Link
              v-if="$page.props.auth.user"
              :href="route('dashboard')"
              class="text-sm font-medium text-gray-600 hover:text-gray-900 transition"
            >ダッシュボード</Link>
            <template v-else>
              <Link :href="route('login')"
                class="px-5 py-2 text-sm font-semibold text-gray-700 hover:text-gray-900 transition">
                ログイン
              </Link>
            </template>
          </template>
        </div>
      </div>
    </header>

    <!-- ヒーロー -->
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-slate-50"></div>
      <div class="absolute top-20 left-1/4 w-96 h-96 bg-indigo-100 rounded-full blur-3xl opacity-40"></div>
      <div class="absolute bottom-10 right-1/4 w-80 h-80 bg-purple-100 rounded-full blur-3xl opacity-30"></div>

      <div class="relative max-w-5xl mx-auto px-6 pt-24 pb-20 text-center">
        <p class="inline-block text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-4 py-1.5 rounded-full mb-8 tracking-wide">
          放課後等デイサービス専用
        </p>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-[1.15] mb-6 tracking-tight">
          支援の記録を、<br>もっとスマートに。
        </h1>
        <p class="text-base sm:text-lg text-gray-500 mb-12 max-w-2xl mx-auto leading-relaxed">
          出席管理から個別支援計画まで、放デイの日常業務をひとつに集約。<br class="hidden sm:block">
          AIが記録の下書きを自動生成し、スタッフの負担を大幅に削減します。
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
          <a href="#pricing"
            class="px-8 py-3.5 bg-gray-900 text-white text-sm font-bold rounded-full hover:bg-gray-800 transition shadow-lg shadow-gray-900/10">
            料金プランを見る
          </a>
          <a href="#features"
            class="px-8 py-3.5 text-sm font-bold text-gray-600 rounded-full border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition">
            機能を詳しく見る
          </a>
        </div>
      </div>
    </section>

    <!-- 課題提起 -->
    <section class="py-20 px-6">
      <div class="max-w-5xl mx-auto">
        <div class="text-center mb-14">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-3">こんなお悩みはありませんか？</h2>
          <p class="text-sm text-gray-400">多くの事業所が抱える課題を、ハグくむがまとめて解決します</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-5">
          <div v-for="(item, i) in [
            { q: '記録に時間がかかりすぎる', s: '支援記録・個別支援計画・モニタリング…書類業務が多くて残業が続いている' },
            { q: '情報がバラバラで見つからない', s: 'Excelやノートに分散した児童情報、受給者証の期限管理が追いつかない' },
            { q: '複数スタッフで共有できない', s: 'その場にいないスタッフへの申し送りが口頭頼みで伝わっていない' },
          ]" :key="i"
            class="relative bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
            <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="font-bold text-gray-900 mb-2 text-sm">{{ item.q }}</h3>
            <p class="text-sm text-gray-500 leading-relaxed">{{ item.s }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 機能一覧 -->
    <section id="features" class="py-20 px-6 bg-gray-50/70">
      <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-3">主な機能</h2>
          <p class="text-sm text-gray-400">放デイの業務に特化した機能をすべて搭載</p>
        </div>

        <div class="space-y-12">
          <div v-for="(f, i) in features" :key="f.title"
            :class="[
              'flex flex-col gap-6 items-center bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden',
              i % 2 === 0 ? 'sm:flex-row' : 'sm:flex-row-reverse'
            ]">
            <!-- 画像エリア -->
            <div class="sm:w-1/2 w-full bg-gray-100 aspect-video flex items-center justify-center shrink-0">
              <img :src="f.image" :alt="f.title" class="w-full h-full object-cover"
                @error="(e) => e.target.style.display='none'">
              <div class="text-gray-300 text-sm font-medium select-none">
                <!-- 画像が読み込めない場合のフォールバック -->
              </div>
            </div>
            <!-- テキストエリア -->
            <div class="sm:w-1/2 w-full p-6 sm:p-10">
              <div class="inline-block text-[10px] font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full mb-4 tracking-wide">
                機能 {{ String(i + 1).padStart(2, '0') }}
              </div>
              <h3 class="text-lg font-bold text-gray-900 mb-3">{{ f.title }}</h3>
              <p class="text-sm text-gray-500 leading-relaxed">{{ f.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- AI機能ハイライト -->
    <section class="py-20 px-6">
      <div class="max-w-4xl mx-auto">
        <div class="relative bg-gradient-to-br from-gray-900 to-gray-800 rounded-3xl p-10 sm:p-16 text-white overflow-hidden">
          <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
          <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-500 rounded-full blur-3xl opacity-15"></div>

          <div class="relative">
            <h2 class="text-2xl sm:text-3xl font-extrabold mb-4 leading-tight">
              記録の下書きを<br>AIが自動生成
            </h2>
            <p class="text-gray-400 leading-relaxed text-sm sm:text-base max-w-xl">
              日々の支援記録と前回の個別支援計画・モニタリング記録をもとに、
              AIが次回分の下書きを自動作成。長期目標・短期目標・支援方針・支援内容を
              ワンクリックで生成し、そのまま編集して保存できます。
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <span v-for="tag in ['個別支援計画', 'モニタリング記録', '支援方針の提案']" :key="tag"
                class="text-xs bg-white/10 border border-white/10 text-gray-300 px-4 py-2 rounded-full">
                {{ tag }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 料金 -->
    <section id="pricing" class="py-20 px-6 bg-gray-50/70">
      <div class="max-w-xl mx-auto text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-3">料金プラン</h2>
        <p class="text-sm text-gray-400 mb-12">シンプルなワンプラン。すべての機能をご利用いただけます。</p>

        <div class="bg-white rounded-3xl border border-gray-200 shadow-lg p-10">
          <p class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full inline-block mb-6 tracking-wide">すべて込み</p>
          <div class="flex items-end justify-center gap-1 mb-2">
            <span class="text-5xl font-extrabold text-gray-900 tracking-tight">6,980</span>
            <span class="text-lg text-gray-400 font-medium mb-1.5">円 / 月</span>
          </div>
          <p class="text-xs text-gray-400 mb-8">税込価格</p>

          <ul class="space-y-3 text-left max-w-xs mx-auto mb-10">
            <li v-for="item in [
              '全機能利用可能',
              'AI自動下書き機能',
              '職員アカウント無制限',
              '送迎予約システム連携',
              'メール・チャットサポート',
            ]" :key="item" class="flex items-center gap-3 text-sm text-gray-600">
              <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              {{ item }}
            </li>
          </ul>

          <button @click="startCheckout" :disabled="isProcessing"
            class="block w-full py-3.5 bg-gray-900 text-white text-sm font-bold rounded-full hover:bg-gray-800 transition disabled:opacity-50">
            {{ isProcessing ? '処理中...' : '申し込みはこちら' }}
          </button>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 px-6">
      <div class="max-w-2xl mx-auto">
        <div class="text-center mb-14">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-3">よくある質問</h2>
        </div>
        <div class="space-y-4">
          <details v-for="faq in faqs" :key="faq.q"
            class="group bg-white border border-gray-100 rounded-2xl shadow-sm">
            <summary class="flex items-center justify-between cursor-pointer p-6 text-sm font-semibold text-gray-900 select-none">
              {{ faq.q }}
              <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
            </summary>
            <div class="px-6 pb-6 text-sm text-gray-500 leading-relaxed -mt-1">
              {{ faq.a }}
            </div>
          </details>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 px-6">
      <div class="max-w-2xl mx-auto text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-4">
          放デイの業務を、<br>もっとシンプルに。
        </h2>
        <p class="text-sm text-gray-400 mb-8">月額6,980円ですべての機能をご利用いただけます。</p>
        <button @click="startCheckout" :disabled="isProcessing"
          class="inline-block px-10 py-4 bg-gray-900 text-white text-sm font-bold rounded-full hover:bg-gray-800 transition shadow-lg shadow-gray-900/10 disabled:opacity-50">
          {{ isProcessing ? '処理中...' : '申し込みはこちら' }}
        </button>
      </div>
    </section>

    <!-- フッター -->
    <footer class="py-10 px-6 border-t border-gray-100">
      <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-400">
        <span class="font-extrabold text-sm tracking-tight"><span class="bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">ハグ</span><span class="text-gray-900">くむ</span></span>
        <span>&copy; 2026 ハグくむ All rights reserved.</span>
      </div>
    </footer>

  </div>
</template>
