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
  await page.getByRole('button', {name:'ログイン', exact:true}).click();
  if (role === 'inactive') { await expect(page).toHaveURL(/\/dashboard$/); return; }
  await expect(page.getByRole('heading', {name:'ダッシュボード', exact:true})).toBeVisible();
}
async function visit(page, path, heading) {
  const r = await page.goto(path);
  expect(r.status(), path).toBe(200);
  if (heading) await expect(page.getByRole('heading', {name:heading,exact:true})).toBeVisible();
}
function field(page, label, control='input') {
  return page.locator('form label').filter({hasText:new RegExp(`^${label}(?: \\*)?$`)}).locator('..').locator(control);
}
function ownChild() { return seeded.children.find(c=>c.name==='架空児童a-active'); }
function foreignChild() { return seeded.children.find(c=>c.name==='架空児童b-active'); }
function school(tenant) { return seeded.schools.find(s=>s.name===`架空学校${tenant}`); }
async function csrfRequest(page, method, path, data) {
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  return page.request.fetch(path, {method,data, headers:{Accept:'application/json','X-CSRF-TOKEN':token},maxRedirects:0});
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

test('login rejects wrong password, permits real login and logout', async ({page,isMobile}) => {
  await visit(page,'/login');
  await page.locator('#email').fill('admin-a@example.test');
  await page.locator('#password').fill('wrong-password');
  const rejection = page.waitForResponse(r=>new URL(r.url()).pathname==='/login' && r.request().method()==='POST');
  await page.getByRole('button',{name:'ログイン',exact:true}).click();
  await rejection;
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.locator('form')).toBeVisible();
  await login(page);
  if (isMobile) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  else await page.getByRole('button',{name:'架空職員a-admin',exact:true}).click();
  await page.getByRole('button',{name:'ログアウト',exact:true}).filter({visible:true}).click();
  await expect(page).toHaveURL('http://127.0.0.1:8765/');
  await page.goto('/children');
  await expect(page).toHaveURL(/\/login$/);
});

test('register child through form, persist dates, school and weekly schedule', async ({page}) => {
  await login(page);
  await visit(page,'/children','利用児童一覧');
  await page.getByRole('link',{name:'＋ 新規登録',exact:true}).click();
  await page.getByPlaceholder('例：山田 太郎').fill('架空新規児童');
  await page.getByPlaceholder('例：ヤマダ タロウ').fill('カクウシンキジドウ');
  await field(page,'生年月日').fill('2018-05-06');
  await field(page,'契約開始日').fill('2026-07-01');
  await field(page,'学校','select').selectOption(String(school('a').id));
  await page.locator('input[type="checkbox"][value="mon"]').check();
  await page.getByRole('button',{name:'登録する',exact:true}).click();
  await expect(page).toHaveURL(/\/children$/);
  await expect(page.getByRole('link',{name:'架空新規児童',exact:true})).toBeVisible();
  const s=snapshot(), child=s.children.find(c=>c.name==='架空新規児童');
  expect(child.facility_id).toBe(seeded.facilities[0].id);
  expect(child.school_id).toBe(school('a').id);
  expect(child.birthdate.slice(0,10)).toBe('2018-05-06');
  expect(child.contract_start_date.slice(0,10)).toBe('2026-07-01');
  expect(s.schedules.filter(r=>r.child_id===child.id).map(r=>r.day_of_week)).toEqual(['mon']);
});

test('edit displays existing dates and persists an unrelated change without losing them', async ({page}) => {
  await login(page);
  await visit(page,`/children/${ownChild().id}/edit`);
  await expect(field(page,'生年月日')).toHaveValue('2017-04-02');
  await expect(field(page,'契約開始日')).toHaveValue('2026-04-01');
  await field(page,'児童名').fill('架空児童変更後');
  await field(page,'学校','select').selectOption('');
  await page.getByRole('button',{name:'更新する',exact:true}).click();
  await expect(page.getByRole('heading',{name:'架空児童変更後',exact:true})).toBeVisible();
  await page.reload();
  await expect(page.getByRole('heading',{name:'架空児童変更後',exact:true})).toBeVisible();
  const c=snapshot().children.find(c=>c.id===ownChild().id);
  expect(c.school_id).toBeNull(); expect(c.birthdate.slice(0,10)).toBe('2017-04-02');
  expect(c.contract_start_date.slice(0,10)).toBe('2026-04-01');
});

test('invalid child form reports errors and writes no child', async ({page}) => {
  await login(page); await visit(page,'/children/create','児童登録');
  await page.getByPlaceholder('例：ヤマダ タロウ').fill('invalid kana');
  await page.getByRole('button',{name:'登録する',exact:true}).click();
  await expect(page.locator('li').filter({hasText:'児童名'}).first()).toBeVisible();
  expect(snapshot().children).toEqual(seeded.children);
});

test('child search, contract filter and school choices stay within the facility', async ({page}) => {
  await login(page); await visit(page,'/children','利用児童一覧');
  await expect(page.locator('tbody')).not.toContainText('架空児童b');
  await page.getByPlaceholder('名前・カナで検索').fill('架空児童a-ended');
  await page.getByRole('button',{name:'検索',exact:true}).click();
  await expect(page.locator('tbody')).toContainText('架空児童a-ended');
  await expect(page.locator('tbody')).not.toContainText('架空児童a-active');
  await page.getByPlaceholder('名前・カナで検索').fill('');
  await page.locator('select').selectOption('active');
  await expect(page.locator('tbody')).toContainText('架空児童a-active');
  await expect(page.locator('tbody')).not.toContainText('架空児童a-ended');
  await visit(page,'/children/create');
  await expect(field(page,'学校','select').locator('option')).toHaveText(['選択してください','架空学校a']);
});

test('school creation through UI is scoped and visible after reload', async ({page}) => {
  await login(page); await visit(page,'/schools/create','学校登録');
  await field(page,'学校名').fill('架空追加学校');
  await page.getByRole('button',{name:'登録する',exact:true}).click();
  await expect(page).toHaveURL(/\/schools$/); await page.reload();
  await expect(page.locator('tbody')).toContainText('架空追加学校');
  await expect(page.locator('tbody')).not.toContainText('架空学校b');
  expect(snapshot().schools.find(s=>s.name==='架空追加学校').facility_id).toBe(seeded.facilities[0].id);
});

test('admin can add a staff member without exposing the other facility', async ({page}) => {
  await login(page); await visit(page,'/staff','職員管理');
  await expect(page.locator('tbody')).not.toContainText('架空職員b');
  await page.getByRole('link',{name:'＋ 職員追加',exact:true}).click();
  await page.getByPlaceholder('例：山田太郎').fill('架空追加職員');
  await page.getByPlaceholder('例：yamada@example.com').fill('added@example.test');
  await page.getByRole('button',{name:'登録する',exact:true}).click();
  await expect(page).toHaveURL(/\/staff$/);
  await expect(page.locator('tbody')).toContainText('架空追加職員');
  const staff=snapshot().staff.find(s=>s.name==='架空追加職員');
  expect(staff.facility_id).toBe(seeded.facilities[0].id); expect(staff.role).toBe('staff');
});

test('leader can edit own child but cannot open staff management', async ({page}) => {
  await login(page,'leader'); await visit(page,`/children/${ownChild().id}/edit`);
  await field(page,'児童名').fill('架空児発管編集');
  await page.getByRole('button',{name:'更新する',exact:true}).click();
  await expect(page.getByRole('heading',{name:'架空児発管編集',exact:true})).toBeVisible();
  expect((await page.goto('/staff')).status()).toBe(403);
});

test('ordinary staff can read but cannot create edit or delete a child', async ({page}) => {
  await login(page,'staff'); await visit(page,'/children','利用児童一覧');
  await expect(page.getByRole('link',{name:'＋ 新規登録',exact:true})).toHaveCount(0);
  await page.getByRole('link',{name:ownChild().name,exact:true}).click();
  await expect(page.getByRole('button',{name:'削除',exact:true})).toHaveCount(0);
  const r=await csrfRequest(page,'POST','/children',{name:'拒否対象',contract_status:'active'});
  expect(r.status()).toBe(403);
  expect((await page.goto(`/children/${ownChild().id}/edit`)).status()).toBe(403);
  expect(snapshot().children).toEqual(seeded.children);
});

test('direct foreign child and school paths cannot disclose or modify records', async ({page}) => {
  await login(page);
  const rejected=await csrfRequest(page,'PATCH',`/children/${foreignChild().id}`,{name:'改ざん',contract_status:'active'});
  expect(rejected.status()).toBe(403);
  expect((await page.goto(`/children/${foreignChild().id}`)).status()).toBe(403);
  await expect(page.locator('body')).not.toContainText('架空児童b');
  expect((await page.goto(`/schools/${school('b').id}/edit`)).status()).toBe(403);
  expect(snapshot().children).toEqual(seeded.children);
});

test('inactive staff cannot load business data even with correct login credentials', async ({page}) => {
  await login(page,'inactive');
  expect((await page.goto('/children')).status()).toBe(403);
  await expect(page.locator('body')).not.toContainText('架空児童a');
});

test('admin deactivation blocks an already logged-in staff member on next request', async ({page,browser}) => {
  const other=await browser.newContext({baseURL:'http://127.0.0.1:8765',serviceWorkers:'block'});
  await isolate(other);
  try {
    const staffPage=await other.newPage(); await login(staffPage,'staff');
    await visit(staffPage,'/children','利用児童一覧');
    await login(page);
    const member=seeded.staff.find(s=>s.name==='架空職員a-staff');
    await visit(page,`/staff/${member.id}/edit`,'職員編集');
    await page.getByLabel('無効',{exact:true}).check();
    await page.getByRole('button',{name:'更新する',exact:true}).click();
    await expect(page).toHaveURL(/\/staff$/);
    expect(snapshot().staff.find(s=>s.id===member.id).is_active).toBe(false);
    expect((await staffPage.reload()).status()).toBe(403);
  } finally { await other.close(); }
});

test('attendance save and support record survive navigation and reload', async ({page}) => {
  await login(page,'staff');
  await visit(page,'/usage-records?date=2026-07-01','出席管理');
  await expect(page.locator('main')).not.toContainText('架空児童b');
  const saved=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
  await page.getByRole('button',{name:'出席',exact:true}).click();
  expect((await saved).status()).toBe(200);
  await page.reload();
  await page.getByRole('link',{name:'支援記録を入力',exact:true}).click();
  await page.getByPlaceholder('今日の行動・様子を記入してください').fill('架空の支援記録です');
  await page.getByRole('button',{name:'記録を保存',exact:true}).click();
  await expect(page).toHaveURL(/\/usage-records\?date=2026-07-01$/);
  await page.reload();
  await page.getByRole('link',{name:'記録を見る',exact:true}).click();
  await expect(page.locator('body')).toContainText('架空の支援記録です');
  const s=snapshot(); expect(s.usage).toHaveLength(1); expect(s.support).toHaveLength(1);
  expect(s.usage[0].status).toBe('attended'); expect(s.usage[0].facility_id).toBe(seeded.facilities[0].id);
  expect(s.support[0].child_id).toBe(ownChild().id); expect(s.support[0].usage_record_id).toBe(s.usage[0].id);
});

test('invalid CSV rejects all rows rather than saving a partial import', async ({page}) => {
  await login(page); await visit(page,'/children/bulk');
  await page.locator('input[type="file"]').setInputFiles({name:'fixture.csv',mimeType:'text/csv',buffer:Buffer.from('name,name_kana,school_name\n架空CSV児童,カクウジドウ,架空学校a\n別児童,ベツジドウ,架空学校b\n')});
  await page.getByRole('button',{name:'一括登録',exact:true}).click();
  await expect(page.getByText('エラーが見つかったため、登録は行われませんでした。').first()).toBeVisible();
  expect(snapshot().children).toEqual(seeded.children);
});

test('cancel deletion keeps child, confirm deletion soft-deletes only that child', async ({page}) => {
  await login(page); await visit(page,`/children/${ownChild().id}`);
  page.once('dialog',d=>d.dismiss()); await page.getByRole('button',{name:'削除',exact:true}).click();
  expect(snapshot().children).toEqual(seeded.children);
  page.once('dialog',d=>d.accept()); await page.getByRole('button',{name:'削除',exact:true}).click();
  await expect(page).toHaveURL(/\/children$/);
  const s=snapshot(); expect(s.children.find(c=>c.id===ownChild().id).deleted_at).not.toBeNull();
  expect(s.children.filter(c=>c.id!==ownChild().id)).toEqual(seeded.children.filter(c=>c.id!==ownChild().id));
});

test('major admin pages render actual Vue without server or JavaScript failures', async ({page}) => {
  await login(page);
  for (const [path,heading] of [['/children','利用児童一覧'],['/staff','職員管理'],['/schools','学校管理']]) await visit(page,path,heading);
  for (const path of ['/programs','/inquiries','/contact-notes','/shifts','/facility/edit']) {
    await visit(page,path); await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('#app')).not.toBeEmpty();
  }
});

test('forged foreign school is rejected by real session and CSRF validation', async ({page}) => {
  await login(page); await visit(page,'/children/create');
  const r=await csrfRequest(page,'POST','/children',{name:'不正学校',contract_status:'active',school_id:school('b').id});
  expect(r.status()).toBe(422); expect((await r.json()).errors).toHaveProperty('school_id');
  expect(snapshot().children).toEqual(seeded.children);
});

test('missing CSRF token is rejected without creating a child', async ({page}) => {
  await login(page);
  const r=await page.request.post('/children',{data:{name:'CSRF対象',contract_status:'active'},headers:{Accept:'application/json'},maxRedirects:0});
  expect(r.status()).toBe(419); expect(snapshot().children).toEqual(seeded.children);
});
