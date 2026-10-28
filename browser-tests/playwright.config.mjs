import { defineConfig } from '@playwright/test';
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.SAFE_BROWSER_CI !== '1') throw new Error('browser_ci_only');
export default defineConfig({
  testDir: './specs', workers: 1, retries: 0, forbidOnly: true, maxFailures: 6,
  timeout: 30_000, expect: {timeout: 7_000},
  reporter: [['list'], ['./summary.mjs']],
  use: {baseURL:'http://127.0.0.1:8765', serviceWorkers:'block', trace:'off', screenshot:'off', video:'off'},
  projects: [
    {name:'desktop-chromium', use:{browserName:'chromium', viewport:{width:1280,height:900}}},
    {name:'mobile-chromium', use:{browserName:'chromium', viewport:{width:390,height:844}, isMobile:true, hasTouch:true}},
  ],
  webServer: {command:'php -S 127.0.0.1:8765 router.php', url:'http://127.0.0.1:8765/login',
    reuseExistingServer:false, timeout:30_000, stdout:'ignore', stderr:'ignore'},
});
