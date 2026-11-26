import {assertRegisteredChoiceClipboard} from '../choice-clipboard-check.mjs'
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const fixturePath = fileURLToPath(new URL('../fixture.php', import.meta.url));
const fixture = command => JSON.parse(execFileSync('php', [fixturePath, command], {encoding:'utf8', timeout:30_000}));
const snapshot = () => fixture('snapshot');
const password = 'Fixture-password-123!';
let seeded;
let pageErrors;
let serverErrors;
let unexpectedExternal;

async function isolate(context) {
  await context.route('**/*', route => {
    const u = new URL(route.request().url());
    if (u.origin === 'http://127.0.0.1:8765') return route.continue();
    // Fonts and the globally included Stripe script are deliberately not tested here.
    if (!['fonts.googleapis.com','fonts.gstatic.com','js.stripe.com'].includes(u.hostname)) unexpectedExternal.push(u.hostname);
    return route.abort('blockedbyclient');
  });
  context.on('page', trackPage);
}
function trackPage(page) {
  page.on('pageerror', e => pageErrors.push(e.message));
  page.on('response', r => { if (r.url().startsWith('http://127.0.0.1:8765/') && r.status() >= 500) serverErrors.push(r.status()); });
}
async function login(page, role='admin', tenant='a') {
  const response = await page.goto('/login');
  expect(response.status()).toBe(200);
  await page.locator('#email').fill(`${role}-${tenant}@example.test`);
  await page.locator('#password').fill(password);
  const blocked = role === 'inactive'
    ? page.waitForResponse(r=>new URL(r.url()).pathname==='/dashboard' && r.status()===403) : null;
  await page.getByRole('button', {name:'ログイン', exact:true}).click();
  if (blocked) { await blocked; return; }
  await expect(page.getByRole('heading', {name:'今日の業務', exact:true})).toBeVisible();
}
async function visit(page, path, heading) {
  const r = await page.goto(path);
  expect(r.status(), path).toBe(200);
  if (heading) await expect(page.getByRole('heading', {name:heading,exact:true})).toBeVisible();
}
async function openModule(page, label) {
  await expect(page.locator('.workspace-shell')).toBeVisible();
  const nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  if (!(await nav.isVisible())) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  const destination=nav.getByRole('link',{name:label,exact:true});
  if(!await destination.isVisible()) await destination.locator('xpath=ancestor::details/summary').click();
  await destination.click();
}
function field(page, label, control='input') {
  return page.locator('form label').filter({hasText:new RegExp(`^${label}(?: \\*)?$`)}).locator('..').locator(control);
}
function ownChild() { return seeded.children.find(c=>c.name==='架空児童a-active'); }
function foreignChild() { return seeded.children.find(c=>c.name==='架空児童b-active'); }
function school(tenant) { return seeded.schools.find(s=>s.name===`架空学校${tenant}`); }
async function csrfRequest(page, method, path, data) {
  // Login regenerates the session token; Inertia does not replace the head meta tag.
  const cookie = (await page.context().cookies()).find(c=>c.name==='XSRF-TOKEN');
  expect(cookie).toBeDefined();
  return page.request.fetch(path, {method,data, headers:{Accept:'application/json','X-XSRF-TOKEN':decodeURIComponent(cookie.value)},maxRedirects:0});
}

test.beforeEach(async ({context,page}) => {
  seeded = fixture('reset');
  pageErrors=[]; serverErrors=[]; unexpectedExternal=[];
  await isolate(context); trackPage(page);
});
test.afterEach(() => {
  expect(pageErrors, 'uncaught browser exceptions').toEqual([]);
  expect(serverErrors, 'unexpected server failures').toEqual([]);
  expect(unexpectedExternal, 'unplanned external requests').toEqual([]);
});

test('navigation keeps manually closed daily work and other open groups when opening today and reloading', async ({page,isMobile}) => {
  await login(page);
  const navigation = async () => {
    if(isMobile && !await page.getByRole('dialog',{name:'メニュー',exact:true}).isVisible()) await page.getByRole('button',{name:'メニュー',exact:true}).click();
    return page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  };
  let nav=await navigation();
  await nav.locator('details[aria-label="日々の業務"] > summary').click();
  await nav.locator('details[aria-label="設定"] > summary').click();
  const check=async () => {
    nav=await navigation();
    await expect(nav.locator('details[aria-label="日々の業務"]')).not.toHaveAttribute('open','');
    await expect(nav.locator('details[aria-label="設定"]')).toHaveAttribute('open','');
    await expect(nav.locator('details[aria-label="請求・会計"]')).not.toHaveAttribute('open','');
  };
  await check();
  await Promise.all([
    page.waitForResponse(r=>new URL(r.url()).pathname==='/dashboard' && r.request().headers()['x-inertia']==='true'),
    nav.getByRole('link',{name:'今日の業務',exact:true}).click(),
  ]);
  await check();
  await nav.getByRole('link',{name:'学校',exact:true}).click();
  await expect(page).toHaveURL(/\/schools$/);
  await check();
  await page.reload();await check();
  // Keyboard toggles the focused heading; the other groups stay unchanged.
  await nav.locator('details[aria-label="日々の業務"] > summary').focus();
  await page.keyboard.press('Enter');
  await expect(nav.locator('details[aria-label="日々の業務"]')).toHaveAttribute('open','');
  await nav.getByRole('link',{name:'出欠・利用実績',exact:true}).click();
  await expect(page).toHaveURL(/\/usage-records(?:\?|$)/);
  nav=await navigation();
  await expect(nav.locator('details[aria-label="日々の業務"]')).toHaveAttribute('open','');
  await expect(nav.locator('details[aria-label="設定"]')).toHaveAttribute('open','');
});

test('navigation state is independent for another signed-in user and facility', async ({page,isMobile}) => {
  await login(page);
  if(isMobile) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  const nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await nav.locator('details[aria-label="日々の業務"] > summary').click();
  await nav.locator('details[aria-label="設定"] > summary').click();
  if(!isMobile) await page.getByRole('button',{name:'架空職員a-admin',exact:true}).click();
  await page.getByRole('button',{name:'ログアウト',exact:true}).filter({visible:true}).click();
  await expect(page).toHaveURL('http://127.0.0.1:8765/');
  await login(page,'admin','b');
  if(isMobile) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  const other=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await expect(other.locator('details[aria-label="日々の業務"]')).toHaveAttribute('open','');
  await expect(other.locator('details[aria-label="設定"]')).not.toHaveAttribute('open','');
});

test('desktop and mobile navigation share manually changed group state', async ({page}) => {
  await login(page);await page.setViewportSize({width:1280,height:900});
  let nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await nav.locator('details[aria-label="日々の業務"] > summary').click();
  await expect(nav.locator('details[aria-label="日々の業務"]')).not.toHaveAttribute('open','');
  await page.setViewportSize({width:390,height:844});
  await page.getByRole('button',{name:'メニュー',exact:true}).click();
  nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await expect(nav.locator('details[aria-label="日々の業務"]')).not.toHaveAttribute('open','');
  await nav.locator('details[aria-label="設定"] > summary').click();
  await page.getByRole('button',{name:'閉じる',exact:true}).click();
  await page.setViewportSize({width:1280,height:900});
  nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await expect(nav.locator('details[aria-label="設定"]')).toHaveAttribute('open','');
  await expect(nav.locator('details[aria-label="日々の業務"]')).not.toHaveAttribute('open','');
});

