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
  await expect(page.getByRole('heading', {name:'業務内容確認', exact:true})).toBeVisible();
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
  await nav.getByRole('link',{name:label,exact:true}).click();
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
  await visit(page,'/children','児童の記録');
  await page.getByRole('link',{name:'＋ 新規登録',exact:true}).click();
  await page.getByPlaceholder('例：山田 太郎').fill('架空新規児童');
  await page.getByPlaceholder('例：ヤマダ タロウ').fill('カクウシンキジドウ');
  await field(page,'生年月日').fill('2018-05-06');
  await field(page,'契約開始日').fill('2026-07-01');
  await field(page,'学校','select').selectOption(String(school('a').id));
  await page.locator('input[type="checkbox"][value="mon"]').locator('..').click();
  await expect(page.locator('input[type="checkbox"][value="mon"]')).toBeChecked();
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
  await login(page); await visit(page,'/children','児童の記録');
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
  await login(page,'staff'); await visit(page,'/children','児童の記録');
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
    await visit(staffPage,'/children','児童の記録');
    await login(page);
    const member=seeded.staff.find(s=>s.name==='架空職員a-staff');
    await visit(page,`/staff/${member.id}/edit`,'職員編集');
    await page.locator('label').filter({hasText:/^\s*無効\s*$/}).click();
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
  await page.getByRole('button',{name:'このメンバーで確定',exact:true}).click();
  expect((await saved).status()).toBe(200);
  await page.reload();
  await expect(page.locator('main').getByRole('link',{name:'記録・連絡帳を入力',exact:true})).toHaveCount(0);
  await openModule(page,'記録・連絡帳');
  await page.getByRole('link',{name:'記録・連絡帳を入力',exact:true}).click();
  await page.getByPlaceholder('今日の行動・様子を記入してください').fill('架空の支援記録です');
  const message=page.getByPlaceholder('今日の様子を保護者向けの表現で記入してください（AI下書きも利用できます）');
  await message.fill('架空の保護者向けメッセージです');
  await page.getByRole('checkbox',{name:'保存と同時に保護者へ公開する'}).check();
  await page.getByRole('button',{name:'記録を保存',exact:true}).scrollIntoViewIfNeeded();
  const createScroll=await page.evaluate(()=>window.scrollY);
  expect(createScroll).toBeGreaterThan(100);
  await page.getByRole('button',{name:'記録を保存',exact:true}).click();
  await expect(page).toHaveURL(/\/support-records\/\d+\/edit$/);
  const editUrl=page.url();
  await expect(page.getByRole('status')).toHaveText('支援記録を保存しました。');
  expect(Math.abs(await page.evaluate(()=>window.scrollY)-createScroll)).toBeLessThan(100);
  await expect(message).toHaveValue('架空の保護者向けメッセージです');
  await expect(page.getByText('公開済み',{exact:true})).toBeVisible();
  const id=snapshot().support[0].id;
  await page.getByPlaceholder('今日の行動・様子を記入してください').fill('架空の支援記録に追記しました');
  await message.fill('架空の保護者向けメッセージに追記しました');
  await page.getByRole('button',{name:'記録を保存',exact:true}).scrollIntoViewIfNeeded();
  const updateScroll=await page.evaluate(()=>window.scrollY);
  await page.getByRole('button',{name:'記録を保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('支援記録を更新しました。');
  await expect(page).toHaveURL(editUrl);
  expect(Math.abs(await page.evaluate(()=>window.scrollY)-updateScroll)).toBeLessThan(10);
  await expect(page.getByRole('button',{name:'記録を保存',exact:true})).toBeEnabled();
  await page.reload();
  await expect(page.getByPlaceholder('今日の行動・様子を記入してください')).toHaveValue('架空の支援記録に追記しました');
  await expect(message).toHaveValue('架空の保護者向けメッセージに追記しました');
  await page.getByRole('link',{name:'← 記録・連絡帳へ',exact:true}).click();
  await expect(page).toHaveURL(/\/contact-notes\?date=2026-07-01$/);
  await page.getByRole('link',{name:'記録・連絡帳を編集',exact:true}).click();
  await expect(page.getByPlaceholder('今日の行動・様子を記入してください')).toHaveValue('架空の支援記録に追記しました');
  const s=snapshot(); expect(s.usage).toHaveLength(1); expect(s.support).toHaveLength(1);
  expect(s.support[0].id).toBe(id);
  expect(s.usage[0].status).toBe('attended'); expect(s.usage[0].facility_id).toBe(seeded.facilities[0].id);
  expect(s.support[0].child_id).toBe(ownChild().id); expect(s.support[0].usage_record_id).toBe(s.usage[0].id);
});

test('attendance time options use five minutes and save, reload and clear', async ({page}) => {
  await login(page,'staff');
  await visit(page,'/usage-records?date=2026-07-01','出席管理');
  const arrival=page.getByRole('combobox',{name:ownChild().name+'の来所時刻',exact:true});
  const departure=page.getByRole('combobox',{name:ownChild().name+'の退所時刻',exact:true});
  const expected=['',...Array.from({length:288},(_,i)=>`${String(Math.floor(i/12)).padStart(2,'0')}:${String(i%12*5).padStart(2,'0')}`)];
  for(const control of [arrival,departure]) {
    expect(await control.locator('option').evaluateAll(options=>options.map(o=>o.value))).toEqual(expected);
    await expect(control.locator('option[value="15:01"]')).toHaveCount(0);
  }
  async function choose(control,value) {
    const saved=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
    await control.selectOption(value); expect((await saved).status()).toBe(200);
  }
  await choose(arrival,'15:05'); await choose(departure,'17:35');
  await page.reload();
  await expect(arrival).toHaveValue('15:05'); await expect(departure).toHaveValue('17:35');
  expect(snapshot().usage[0]).toMatchObject({check_in_time:'15:05',check_out_time:'17:35'});
  await choose(arrival,''); await choose(departure,''); await page.reload();
  await expect(arrival).toHaveValue(''); await expect(departure).toHaveValue('');
  expect(snapshot().usage[0]).toMatchObject({check_in_time:null,check_out_time:null});
});

test('existing minute-precision attendance is displayed and preserved until explicitly changed', async ({page}) => {
  fixture('legacy-attendance-times');
  await login(page,'staff');
  await visit(page,'/usage-records?date=2026-07-01','出席管理');
  const arrival=page.getByRole('combobox',{name:ownChild().name+'の来所時刻',exact:true});
  const departure=page.getByRole('combobox',{name:ownChild().name+'の退所時刻',exact:true});
  await expect(arrival).toHaveValue('14:31'); await expect(departure).toHaveValue('17:33');
  await expect(arrival.locator('option:checked')).toHaveText('14:31（既存値）');
  const saved=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
  await page.getByPlaceholder('メモ',{exact:true}).fill('既存時刻の保持確認');
  await page.getByPlaceholder('メモ',{exact:true}).press('Tab');
  expect((await saved).status()).toBe(200);
  await page.reload();
  expect(snapshot().usage[0]).toMatchObject({check_in_time:'14:31',check_out_time:'17:33'});
  const changed=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
  await arrival.selectOption('14:35'); expect((await changed).status()).toBe(200); await page.reload();
  await expect(arrival).toHaveValue('14:35'); await expect(arrival.locator('option[value="14:31"]')).toHaveCount(0);
  await expect(departure).toHaveValue('17:33');
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
  for (const [path,heading] of [['/children','児童の記録'],['/staff','職員管理'],['/schools','学校管理']]) await visit(page,path,heading);
  for (const path of ['/transport','/daily-activities','/programs','/inquiries','/contact-notes','/shifts','/shift-labels','/external-facilities','/facility/edit','/billing','/billing/invoices','/billing/returns','/billing/error-claims','/billing/expenses','/billing/cap-management']) {
    await visit(page,path); await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('#app')).not.toBeEmpty();
    await expect(page.locator('.workspace-shell')).toBeVisible();
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth), path).toBe(true);
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

test('contact publication can be withdrawn and republished without deleting history', async ({page}) => {
  const data=fixture('contact-notes');
  const own=data.notes.find(n=>n.child_id===ownChild().id);
  const foreign=data.notes.find(n=>n.facility_id!==own.facility_id);
  await login(page);
  await visit(page,'/contact-notes?date=2026-07-01','記録・連絡帳');
  page.once('dialog',d=>d.dismiss());
  await page.getByRole('button',{name:'公開を取り下げる',exact:true}).click();
  expect(snapshot().notes.find(n=>n.id===own.id).status).toBe('published');
  page.once('dialog',d=>d.accept());
  const withdrawing=page.waitForResponse(r=>new URL(r.url()).pathname===`/contact-notes/${own.id}/unpublish` && r.request().method()==='POST');
  await page.getByRole('button',{name:'公開を取り下げる',exact:true}).click();
  expect((await withdrawing).status()).toBe(302);
  await expect(page.getByText('取り下げ連携待ち・要確認',{exact:true})).toBeVisible();
  await page.reload();
  expect(snapshot().notes.find(n=>n.id===own.id)).toMatchObject({status:'draft',published_at:null,guardian_message:'架空の公開連絡帳',guardian_comment:'架空の家庭返信'});
  const forbidden=await csrfRequest(page,'POST',`/contact-notes/${foreign.id}/unpublish`,{});
  expect(forbidden.status()).toBe(403);
  expect(snapshot().notes.find(n=>n.id===foreign.id).status).toBe('published');
  page.once('dialog',d=>d.accept());
  const publishing=page.waitForResponse(r=>new URL(r.url()).pathname===`/contact-notes/${own.id}/publish` && r.request().method()==='POST');
  await page.getByRole('button',{name:'公開する',exact:true}).click();
  expect((await publishing).status()).toBe(302);
  await expect(page.getByRole('button',{name:'公開を取り下げる',exact:true})).toBeVisible();
  expect(snapshot().notes.find(n=>n.id===own.id)).toMatchObject({status:'published',withdrawn_at:null,guardian_comment:'架空の家庭返信'});
});

test('staff reviews the morning family entry before any support record is written',async({page})=>{
  fixture('morning-entry'); await login(page,'staff');
  await visit(page,'/contact-notes?date=2026-07-01','記録・連絡帳');
  await expect(page.getByText('架空の利用前の体調連絡',{exact:true})).toBeVisible();
  await expect(page.getByText('体温：36.6',{exact:false})).toBeVisible();
  await expect(page.getByText('保護者確認印済み',{exact:false})).toHaveCount(0);
  await page.getByRole('button',{name:'家庭からの連絡を確認した',exact:true}).click();
  await expect(page.getByText(/事業所確認済み /)).toBeVisible();
  await page.reload(); await expect(page.getByText(/事業所確認済み /)).toBeVisible();
  expect(snapshot().notes[0].guardian_checked_at).not.toBeNull();
  expect(snapshot().notes[0].status).toBe('draft');
});



test('categorized navigation has one child entry, preserves tabs and date', async ({page,isMobile}) => {
  await login(page);
  if (isMobile) {
    const trigger=page.getByRole('button',{name:'メニュー',exact:true});
    await trigger.click();
    await expect(page.getByRole('dialog',{name:'メニュー',exact:true})).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await trigger.click();
  }
  const nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  for(const name of ['ホーム','日々の業務','児発管・管理者業務','請求・会計','事業所運営','設定']) await expect(nav.getByRole('heading',{name,exact:true})).toBeVisible();
  await expect(nav.getByRole('link',{name:'支援計画・モニタリング',exact:true})).toHaveCount(0);
  await expect(nav.getByRole('link',{name:'受給者証・利用契約',exact:true})).toHaveCount(0);
  await expect(nav.locator('a[href*="/children"]')).toHaveCount(1);
  await nav.getByRole('link',{name:'児童の記録',exact:true}).click();
  await expect(page).toHaveURL(/\/children$/);
  const childNav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  if(!await childNav.isVisible()) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  await expect(childNav.getByRole('link',{name:'児童の記録',exact:true})).toHaveAttribute('aria-current','page');
  if(isMobile) await page.getByRole('button',{name:'閉じる',exact:true}).click();
  await page.getByRole('link',{name:ownChild().name,exact:true}).click();
  await page.getByRole('button',{name:/^計画・記録/}).click();
  await expect(page.getByRole('heading',{name:'個別支援計画',exact:true})).toBeVisible();
  await page.reload();
  await expect(page.getByRole('heading',{name:'個別支援計画',exact:true})).toBeVisible();
  await visit(page,'/usage-records?date=2026-07-01','出席管理');
  await expect(page.getByRole('navigation',{name:'同じ日の業務',exact:true})).toHaveCount(0);
  await openModule(page,'記録・連絡帳');
  await expect(page).toHaveURL(/\/contact-notes\?date=2026-07-01$/);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});

test('daily work leads staff from family review to one saved record without losing input', async ({page,isMobile}) => {
  fixture('morning-entry');fixture('legacy-attendance-times');
  await login(page,'staff');
  if(isMobile) await page.getByRole('button',{name:'メニュー',exact:true}).click();
  const nav=page.getByRole('navigation',{name:'業務メニュー',exact:true}).filter({visible:true});
  await expect(nav.getByRole('heading',{name:'請求・会計',exact:true})).toHaveCount(0);
  await expect(nav.getByRole('link',{name:'施設設定',exact:true})).toHaveCount(0);
  if(isMobile) await page.getByRole('button',{name:'閉じる',exact:true}).click();
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  await expect(page.getByRole('navigation', {name:'この日の確認順',exact:true})).toHaveCount(0);
  await expect(page.getByRole('heading',{name:'利用・欠席',exact:true})).toBeVisible();
  await expect(page.getByRole('link',{name:/連絡帳の連携未完了/})).toBeVisible();
  await expect(page.locator('.workspace-shell')).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
  await expect(page.locator('main')).not.toContainText(foreignChild().name);
  const home=page.locator('main');
  await expect(home.getByRole('heading',{name:'未対応業務',exact:true})).toBeVisible();
  await expect(home.getByRole('heading',{name:'対応が必要な児童',exact:true})).toHaveCount(0);
  await expect(home.getByText('児童別の状況',{exact:true})).toHaveCount(0);
  await expect(home.getByRole('button',{name:/全児童の状況/})).toHaveCount(0);
  await expect(home.getByRole('link',{name:'家庭の連絡が未確認 1人',exact:true})).toBeVisible();
  await home.getByRole('link',{name:'支援記録未入力 1人',exact:true}).click();
  await expect(page).toHaveURL(/\/contact-notes\?date=2026-07-01&task=record$/);
  await expect(page.getByRole('status')).toContainText('支援記録未入力');
  await page.getByRole('link',{name:'記録・連絡帳を入力',exact:true}).click();
  const behavior=page.getByPlaceholder('今日の行動・様子を記入してください');
  await behavior.fill('家庭の連絡を読みながら入力した架空の記録');
  const family=page.getByRole('region',{name:'家庭からの連絡',exact:true});
  await expect(family).toContainText('架空の利用前の体調連絡');
  await family.getByRole('button',{name:'家庭からの連絡を確認した',exact:true}).click();
  await expect(family).toContainText('事業所確認済み');
  await expect(behavior).toHaveValue('家庭の連絡を読みながら入力した架空の記録');
  await page.getByRole('button',{name:'記録を保存',exact:true}).click();
  await expect(page).toHaveURL(/\/support-records\/\d+\/edit$/);
  await expect(page.getByRole('status')).toHaveText('支援記録を保存しました。');
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  await expect(home.getByRole('link',{name:'家庭の連絡が未確認 0人',exact:true})).toBeVisible();
  await expect(home.getByRole('link',{name:'支援記録未入力 0人',exact:true})).toBeVisible();
  await home.getByRole('link',{name:'連絡帳未入力 1人',exact:true}).click();
  await expect(page).toHaveURL(/\/contact-notes\?date=2026-07-01&task=report$/);
  await page.getByRole('link',{name:'記録・連絡帳を編集',exact:true}).click();
  await expect(behavior).toHaveValue('家庭の連絡を読みながら入力した架空の記録');
  expect(snapshot().support).toHaveLength(1);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});

test('candidate button shows every staff member without clearing current value; search, keyboard and saving work', async ({page}) => {
  await login(page)
  await visit(page,'/transport?date=2026-07-01','送迎一覧')
  const { checkPicker } = await import('../picker-check.mjs')
  await checkPicker(page, expect)
})


test('dashboard shows all staff transport read-only and editing has one sidebar entry', async ({page}) => {
  fixture('morning-entry'); fixture('legacy-attendance-times');
  await login(page);
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  const transport=page.locator('#transport-work');
  await expect(transport.getByText('入力・変更先：メニュー「送迎一覧」',{exact:true})).toBeVisible();
  await expect(transport.getByText('送迎担当を割り当てる',{exact:true})).toHaveCount(0);
  await expect(page.locator('main a[href*="/support-records/"]')).toHaveCount(0);
  await expect(transport.getByRole('link',{name:'送迎一覧',exact:true})).toBeVisible();
  await openModule(page,'送迎一覧');
  await expect(page).toHaveURL(/\/transport\?date=2026-07-01$/);
  await page.getByText('送迎担当を割り当てる',{exact:true}).click();
  const form=page.locator('main form');
  await form.getByLabel('児童',{exact:true}).selectOption(String(ownChild().id));
  await form.getByLabel('担当職員',{exact:true}).selectOption(String(seeded.staff.find(s=>s.name==='架空職員a-staff').id));
  await form.getByLabel('時刻',{exact:true}).fill('14:30');
  await form.getByLabel('場所',{exact:true}).fill('学校');
  await form.getByRole('button',{name:'保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('送迎担当を更新しました。');
  await expect(page).toHaveURL(/\/transport\?date=2026-07-01$/);
  await openModule(page,'業務内容確認');
  await expect(transport.getByRole('cell',{name:'架空職員a-staff',exact:true})).toBeVisible();
  await expect(transport.getByRole('button',{name:'担当変更',exact:true})).toHaveCount(0);
  const headerBox=await transport.locator('.transport-section-header').boundingBox();
  const tableBox=await transport.locator('table').first().boundingBox();
  await expect(transport.getByRole('region',{name:'迎えの送迎',exact:true})).toBeVisible();
  await expect(transport.getByRole('region',{name:'送りの送迎',exact:true})).toBeVisible();
  expect(await transport.locator('table').first().locator('thead th').allTextContents()).toEqual(['時間','場所','担当職員','児童']);
  expect(tableBox.y-headerBox.y-headerBox.height).toBeGreaterThanOrEqual(12);
  await expect(transport).not.toContainText('p-yoyakuと共通の担当です');
});


test('secondary export tools stay closed, open by keyboard, and retain input when closed', async ({page}) => {
  await login(page);
  await openModule(page,'記録・連絡帳');
  const trigger=page.getByRole('button',{name:'年間PDF出力（記録の保存用）',exact:true});
  await expect(trigger).toHaveAttribute('aria-expanded','false');
  const year=page.getByLabel('出力する年',{exact:true});
  await expect(year).not.toBeVisible();
  await trigger.focus(); await page.keyboard.press('Enter');
  await expect(trigger).toHaveAttribute('aria-expanded','true');
  await expect(year).toBeVisible();
  const value=await year.inputValue();
  await trigger.click();
  await expect(year).not.toBeVisible();
  await expect(page.locator('#annual-contact-export')).toHaveAttribute('inert','');
  await trigger.press('Enter');
  await expect(year).toHaveValue(value);
  await page.emulateMedia({reducedMotion:'reduce'});
  expect(await page.locator('#annual-contact-export').evaluate(el=>getComputedStyle(el).transitionDuration)).toBe('0s');
});


test('overview names, menu guidance, no-op allergy notes and role sections stay clear', async ({page,isMobile}) => {
  fixture('no-special-notes');
  await login(page);
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  await expect(page.locator('.workspace-brand-mark')).toHaveCount(0);
  await expect(page.locator('main')).not.toContainText('ここで未対応を把握し');
  await expect(page.locator('main')).not.toContainText('保存済みの出欠');
  await expect(page.locator('#daily-visits').locator('..')).toContainText('利用児童');
  const visits=page.getByRole('region',{name:'利用・欠席',exact:true});
  await expect(visits).not.toContainText('特記事項なし');
  await expect(visits).toContainText('卵アレルギー');
  for (const [id,heading] of [['staff-work','職員の確認事項'],['management-work','管理者・児発管の確認事項'],['office-work','事務の確認事項']]) {
    await expect(page.locator('#'+id).getByRole('heading',{name:heading,exact:true})).toBeVisible();
  }
  const colors=await page.locator('.workspace-role-header').evaluateAll(nodes=>nodes.map(node=>getComputedStyle(node).backgroundColor));
  expect(new Set(colors).size).toBe(3);
  await visits.getByRole('link',{name:'出欠・利用実績',exact:true}).click();
  await expect(page).toHaveURL(/\/usage-records\?date=2026-07-01$/);
  await expect(page.getByRole('article',{name:ownChild().name+'の出席記録',exact:true})).not.toContainText('特記事項なし');
  await expect(page.getByRole('group',{name:ownChild().name+'の利用時間',exact:true})).toBeVisible();
  await expect(page.getByRole('group',{name:ownChild().name+'の送迎・請求',exact:true})).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
  await openModule(page,'業務内容確認');
  await expect(page.getByRole('heading',{name:'業務内容確認',exact:true})).toBeVisible();
  if(isMobile) await expect(page.getByRole('dialog',{name:'メニュー',exact:true})).not.toBeVisible();
});

test('activity edit and delete align and the editor retains its content', async ({page}) => {
  fixture('daily-activity');
  await login(page);
  await visit(page,'/daily-activities?date=2026-07-01','療育予定・年間行事');
  const actions=page.locator('.workspace-row-actions').first();
  const edit=actions.getByRole('link',{name:'編集',exact:true});
  const remove=actions.getByRole('button',{name:'削除',exact:true});
  const e=await edit.boundingBox(), d=await remove.boundingBox();
  expect(Math.abs(e.y-d.y)).toBeLessThan(1);expect(Math.abs(e.height-d.height)).toBeLessThan(1);
  expect(d.x).toBeGreaterThan(e.x+e.width);
  await edit.click();
  await expect(page.getByRole('button',{name:'予定を編集',exact:true})).toHaveAttribute('aria-expanded','true');
  await expect(page.getByPlaceholder('プログラム選択時は省略可')).toHaveValue('架空の読み聞かせ');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});


test('absence contacts keep drafts, appear below visits in one card and are reversible with audit retention', async ({page}) => {
  fixture('morning-entry');
  await login(page);
  await visit(page,'/absences?date=2026-07-01','欠席連絡・相談記録');
  let board=page.locator('.ab-board');
  await expect(board.getByText('営業日を設定・確認',{exact:true})).toHaveCount(0);
  const refresh=board.getByRole('button',{name:'最新の状態に更新',exact:true});
  await expect(refresh).toHaveCSS('border-top-width','0px');
  await expect(refresh).toHaveCSS('background-color','rgba(0, 0, 0, 0)');
  await refresh.click();await expect(board.locator('..')).toHaveAttribute('aria-busy','false');await expect(board.locator('[data-child-select]')).toBeVisible();
  const selector=await board.locator('[data-child-select]').boundingBox(),add=await board.getByRole('button',{name:'欠席記録を追加',exact:true}).boundingBox();
  expect(Math.abs(selector.y-add.y)).toBeLessThan(1);expect(Math.abs(selector.height-add.height)).toBeLessThan(1);
  await board.getByRole('link',{name:'施設設定',exact:true}).click();
  await expect(page).toHaveURL(/\/facility\/edit#absence-calendar$/);
  const calendar=page.getByRole('region',{name:'欠席判定に使う営業日',exact:true});
  for(const day of ['月','火','水','木','金','土','日']) await calendar.getByLabel(day,{exact:true}).check();
  await calendar.getByLabel('臨時休業日（1行に1日、YYYY-MM-DD）',{exact:true}).fill('2026-07-06');
  await calendar.getByRole('button',{name:'営業日を確認して保存',exact:true}).click();
  await expect(calendar.getByRole('status')).toHaveText('営業日を保存しました。');
  await page.reload();await expect(calendar.getByLabel('月',{exact:true})).toBeChecked();
  await expect(calendar.getByLabel('臨時休業日（1行に1日、YYYY-MM-DD）',{exact:true})).toHaveValue('2026-07-06');
  await visit(page,'/absences?date=2026-07-01','欠席連絡・相談記録');
  board=page.locator('.ab-board');
  await board.locator('[data-child-select]').selectOption(String(ownChild().id));
  await board.getByRole('button',{name:'欠席記録を追加',exact:true}).click();
  const form=board.locator('form[data-child]');
  await form.getByLabel('欠席理由',{exact:true}).fill('発熱のため自宅で休養');
  await form.getByLabel('連絡日時（日本時間）',{exact:true}).fill('2026-07-01T09:00');
  await form.getByLabel('連絡手段',{exact:true}).selectOption('line');
  await form.getByLabel('連絡者（母・父など）',{exact:true}).fill('母');
  await form.getByRole('button',{name:'この記録を保存',exact:true}).click();
  await expect(board.getByRole('status')).toHaveText('保存しました。');
  await expect(board.locator('summary').first()).toContainText('記録不足');
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  const summary=page.getByRole('region',{name:'利用・欠席',exact:true});
  const counts=page.locator('[aria-label="利用・欠席の集計"]');
  await expect(counts).toContainText('契約中');
  await expect(counts).toContainText('利用');
  await expect(counts).toContainText('欠席 1人');
  await expect(counts).not.toContainText('出席');
  await expect(summary).toContainText(ownChild().name);
  await expect(summary).toContainText('発熱のため自宅で休養');
  await expect(summary).toContainText('LINE');
  await expect(summary.locator('.daily-visits-list').getByText(ownChild().name,{exact:true})).toHaveCount(0);
  const countsY=await counts.boundingBox(), summaryY=await summary.boundingBox(), visitsY=await page.locator('#daily-visits').boundingBox(), absencesY=await page.locator('#daily-absences').boundingBox(), transportY=await page.locator('#staff-transport').boundingBox(), activityY=await page.locator('#activity-preparation').boundingBox();
  expect(countsY.y+countsY.height).toBeLessThanOrEqual(summaryY.y);expect(visitsY.y).toBeLessThan(absencesY.y);expect(summaryY.y+summaryY.height).toBeLessThanOrEqual(transportY.y);expect(transportY.y).toBeLessThan(activityY.y);
  await visit(page,'/absences?date=2026-07-01','欠席連絡・相談記録');
  await board.locator('.ab-record > summary').click();
  await form.getByLabel('欠席として記録する（解除すると欠席取消。記録は保持）',{exact:true}).uncheck();
  await form.getByRole('button',{name:'この記録を保存',exact:true}).click();
  await expect(board.getByRole('status')).toHaveText('保存しました。');
  await expect(board.locator('.ab-record > summary')).toContainText('欠席取消');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});


test('repeated absence clicks stay on the same child, highlight immediately and persist only that child', async ({page}) => {
  fixture('attendance-roster');
  const before=snapshot();
  await login(page,'staff');
  await visit(page,'/usage-records?date=2026-12-02','出席管理');
  const cards=page.locator('.attendance-entry');
  const order=await cards.evaluateAll(rows=>rows.map(r=>r.getAttribute('aria-label')));
  expect(order).toHaveLength(3);
  const first=cards.first();
  const targetName=(await first.getAttribute('aria-label')).replace(/の出席記録$/,'');
  const target=before.children.find(c=>c.name===targetName);
  await expect(page.locator('main')).not.toContainText('アレルギー');
  await expect(page.locator('main')).not.toContainText('架空の配慮情報');
  const button=first.getByRole('button',{name:'欠席（連絡あり）',exact:true});
  await button.scrollIntoViewIfNeeded();
  const rect=await button.boundingBox();
  const saved=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
  // The reported failure is repeated clicks at the same physical position.
  for(let i=0;i<5;i++) { await page.mouse.click(rect.x+rect.width/2,rect.y+rect.height/2); }
  expect((await saved).status()).toBe(200);
  expect(await cards.evaluateAll(rows=>rows.map(r=>r.getAttribute('aria-label')))).toEqual(order);
  await expect(button).toHaveAttribute('aria-pressed','true');
  await expect(button).toHaveClass(/bg-amber-50/);
  await expect(button).toHaveCSS('background-color','rgb(255, 251, 235)');
  const counts=page.locator('.grid.grid-cols-4').first().locator(':scope > div');
  await expect(counts.nth(1).locator('.text-2xl')).toHaveText('2');
  await expect(counts.nth(2).locator('.text-2xl')).toHaveText('1');
  await expect(cards.locator('[aria-pressed="true"]').filter({hasText:'欠席（連絡あり）'})).toHaveCount(1);
  for(const row of before.usage) expect(snapshot().usage.find(r=>r.id===row.id)).toMatchObject({status:row.child_id===target.id?'absent_notice':row.status});
  await page.reload();
  const targetCard=page.getByRole('article',{name:targetName+'の出席記録',exact:true});
  await expect(targetCard.getByRole('button',{name:'欠席（連絡あり）',exact:true})).toHaveAttribute('aria-pressed','true');
  // Each choice affects this child only, including returning to attended.
  for(const [label,status] of [['無断欠席','absent'],['キャンセル','cancel'],['出席','attended']]) {
    const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/usage-records/bulk-store' && r.request().method()==='POST');
    await targetCard.getByRole('button',{name:label,exact:true}).click();
    expect((await response).status()).toBe(200);
    await expect(targetCard).toHaveAttribute('data-attendance-status',status);
    for(const row of before.usage) expect(snapshot().usage.find(r=>r.id===row.id)).toMatchObject({status:row.child_id===target.id?status:row.status});
  }
});

test('rapid attendance edits wait for the preceding save and persist the latest selection', async ({page}) => {
  fixture('attendance-roster');
  await login(page,'staff');await visit(page,'/usage-records?date=2026-12-02','出席管理');
  let release;
  const gate=new Promise(resolve=>{release=resolve});
  const writes=[];
  await page.route('**/usage-records/bulk-store',async route=>{
    writes.push(route.request().postDataJSON());
    const response=await route.fetch();
    if(writes.length===1) await gate;
    await route.fulfill({response});
  });
  const row=page.getByRole('article',{name:ownChild().name+'の出席記録',exact:true});
  try {
    await row.getByRole('button',{name:'欠席（連絡あり）',exact:true}).click();
    await expect.poll(()=>writes.length).toBe(1);
    await row.getByRole('button',{name:'出席',exact:true}).click();
    await row.getByRole('button',{name:'無断欠席',exact:true}).click();
    await row.getByRole('button',{name:'欠席（連絡あり）',exact:true}).click();
    await expect(row.getByRole('button',{name:'欠席（連絡あり）',exact:true})).toHaveAttribute('aria-pressed','true');
    expect(writes).toHaveLength(1);
  } finally { release(); }
  await expect.poll(()=>writes.length).toBe(2);
  await expect(page.getByText('✓ 保存完了',{exact:true}).first()).toBeVisible();
  expect(writes[1].records.find(r=>r.child_id===ownChild().id).status).toBe('absent_notice');
  await page.reload();await expect(row).toHaveAttribute('data-attendance-status','absent_notice');
  expect(snapshot().usage.filter(r=>r.status!=='attended').map(r=>r.child_id)).toEqual([ownChild().id]);
});

test('failed attendance save keeps the edit visible and can be explicitly retried', async ({page}) => {
  fixture('attendance-roster');await login(page,'staff');await visit(page,'/usage-records?date=2026-12-02','出席管理');
  let fail=true;
  await page.route('**/usage-records/bulk-store',route=>fail?route.fulfill({status:419,contentType:'application/json',body:'{"message":"expired"}'}):route.continue());
  const row=page.getByRole('article',{name:ownChild().name+'の出席記録',exact:true});
  await row.getByRole('button',{name:'欠席（連絡あり）',exact:true}).click();
  await expect(page.getByRole('alert')).toContainText('入力内容は画面に残っています');
  await expect(row).toHaveAttribute('data-attendance-status','absent_notice');
  expect(snapshot().usage.find(r=>r.child_id===ownChild().id).status).toBe('attended');
  fail=false;
  await page.getByRole('button',{name:'再保存',exact:true}).click();
  await expect(page.getByText('✓ 保存完了',{exact:true}).first()).toBeVisible();
  await page.reload();await expect(row).toHaveAttribute('data-attendance-status','absent_notice');
});


test('contact delivery shows safe reasons, retries without changing publication and refreshes completion', async ({page}) => {
  fixture('contact-delivery');
  await login(page,'staff');
  await visit(page,'/contact-notes?date=2026-07-01','記録・連絡帳');
  const row=page.locator('#child-'+ownChild().id);
  await expect(row).toContainText('p-yoyakuで認証されませんでした。');
  await expect(page.locator('body')).not.toContainText('synthetic-private-token');
  const before=snapshot().notes.find(n=>n.child_id===ownChild().id);
  page.once('dialog',d=>d.dismiss());
  await row.getByRole('button',{name:'連携を再試行',exact:true}).click();
  await expect(row.getByRole('button',{name:'連携を再試行',exact:true})).toBeEnabled();
  page.once('dialog',d=>d.accept());
  await row.getByRole('button',{name:'連携を再試行',exact:true}).click();
  await expect(row).toContainText('送信処理の完了待ち');
  await expect(row.getByRole('button',{name:'連携を再試行',exact:true})).toBeDisabled();
  const after=snapshot().notes.find(n=>n.id===before.id);
  expect(after).toMatchObject({status:before.status,published_at:before.published_at,guardian_message:before.guardian_message});
  fixture('contact-delivery-complete');
  await page.getByRole('button',{name:'最新の状態に更新',exact:true}).click();
  await expect(row.getByText('連携完了',{exact:true})).toBeVisible();
  await expect(row.getByRole('button',{name:'連携を再試行',exact:true})).toHaveCount(0);
  await expect(page).toHaveURL(/\/contact-notes\?date=2026-07-01$/);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});


test('monthly school calendar supports cells, Excel paste and bulk settings and attendance is read-only',async({page})=>{
  fixture('school-calendar');await login(page);
  await visit(page,'/school-calendar?month=2026-07','学校日・休業日設定');
  const filterBox=await page.getByLabel('学校で絞り込み',{exact:true}).boundingBox();
  const bulkBox=await page.getByRole('button',{name:'一括設定・コピペの使い方',exact:true}).boundingBox();
  expect(filterBox.y+filterBox.height).toBeLessThan(bulkBox.y);
  const first=page.getByRole('checkbox',{name:ownChild().name+' 2026-07-01',exact:true});
  await expect(first).toHaveJSProperty('indeterminate',true);
  await first.evaluate(node=>{const data=new DataTransfer();data.setData('text/plain','学校日\t休業日');node.dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}))});
  await expect(first).toBeChecked();
  await expect(page.getByRole('checkbox',{name:ownChild().name+' 2026-07-02',exact:true})).not.toBeChecked();
  await page.getByRole('button',{name:'変更をまとめて保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('学校日・休業日を保存しました。');
  await page.reload();await expect(first).toBeChecked();
  const schoolFilter=page.getByLabel('学校で絞り込み',{exact:true});
  const selectAll=page.getByRole('checkbox',{name:'表示中の児童を全選択',exact:true});
  await schoolFilter.selectOption('学校未登録');await selectAll.check();
  await schoolFilter.selectOption('');await expect(selectAll).not.toBeChecked();
  await expect(page.getByRole('checkbox',{name:ownChild().name+'を選択',exact:true})).not.toBeChecked();
  await expect(first).toBeChecked();
  await page.getByRole('checkbox',{name:ownChild().name+'を選択',exact:true}).check();
  await page.getByRole('button',{name:'一括設定・コピペの使い方',exact:true}).click();
  await page.getByLabel('開始日',{exact:true}).fill('2026-07-03');await page.getByLabel('終了日',{exact:true}).fill('2026-07-03');
  await page.getByRole('button',{name:'選択した児童に適用',exact:true}).click();
  await expect(page.getByRole('checkbox',{name:ownChild().name+' 2026-07-03',exact:true})).toBeChecked();
  await page.getByRole('button',{name:'変更をまとめて保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('学校日・休業日を保存しました。');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
  await visit(page,'/usage-records?date=2026-07-02','出席管理');
  const article=page.getByRole('article',{name:ownChild().name+'の出席記録',exact:true});
  await expect(article.getByText('休業日',{exact:true})).toBeVisible();
  await expect(article.getByText('放課後等デイサービス',{exact:true})).toBeVisible();
  await expect(article.getByRole('checkbox',{name:'学校日',exact:true})).toHaveCount(0);
  await expect(article.getByRole('combobox',{name:ownChild().name+'のサービス種別',exact:true})).toHaveCount(0);
});

test('monthly school calendar preserves unsaved edits on a revision conflict and refresh needs consent',async({page})=>{
  fixture('school-calendar');await login(page);
  await visit(page,'/school-calendar?month=2026-07','学校日・休業日設定');
  const first=page.getByRole('checkbox',{name:ownChild().name+' 2026-07-01',exact:true});
  await test.step('select school day',async()=>{await first.check()});await test.step('simulate concurrent update',async()=>{fixture('school-calendar-concurrent')});
  await test.step('save with conflicting revision',async()=>{await page.getByRole('button',{name:'変更をまとめて保存',exact:true}).click()});
  await expect(page.getByRole('alert')).toContainText('他の職員が更新しました');await expect(first).toBeChecked();
  await test.step('cancel refresh and retain edits',async()=>{page.once('dialog',d=>d.dismiss());await page.getByRole('button',{name:'最新の状態に更新',exact:true}).click()});
  await expect(first).toBeChecked();
  await test.step('accept refresh and load saved holiday',async()=>{page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'最新の状態に更新',exact:true}).click()});
  await expect(first).not.toBeChecked();await expect(first).toHaveJSProperty('indeterminate',false);await expect(page.getByRole('button',{name:'変更をまとめて保存',exact:true})).toBeDisabled();
});

test('school bulk settings cover December 1 through 31 and preserve dates and children outside a later range',async({page})=>{
  fixture('school-calendar-bulk');await login(page);
  await visit(page,'/school-calendar?month=2026-12','学校日・休業日設定');
  const cell=(name,day)=>page.getByRole('checkbox',{name:`${name} 2026-12-${String(day).padStart(2,'0')}`,exact:true});
  await page.getByRole('checkbox',{name:ownChild().name+'を選択',exact:true}).check();
  await page.getByRole('button',{name:'一括設定・コピペの使い方',exact:true}).click();
  await page.getByRole('button',{name:'すべての曜日',exact:true}).click();
  await page.getByRole('button',{name:'選択した児童に適用',exact:true}).click();
  for(let day=1;day<=31;day++)await expect(cell(ownChild().name,day)).toBeChecked();
  await expect(cell('架空の一括設定対象外児童',1)).toHaveJSProperty('indeterminate',true);
  await page.getByLabel('開始日',{exact:true}).fill('2026-12-07');
  await page.getByLabel('終了日',{exact:true}).fill('2026-12-11');
  await page.getByLabel('設定値',{exact:true}).selectOption('holiday');
  await page.getByRole('button',{name:'選択した児童に適用',exact:true}).click();
  await page.getByRole('button',{name:'変更をまとめて保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('学校日・休業日を保存しました。');
  await page.reload();
  for(let day=1;day<=31;day++){
    await expect(cell(ownChild().name,day)).toHaveJSProperty('checked',day<7||day>11);
    await expect(cell(ownChild().name,day)).toHaveJSProperty('indeterminate',false);
    await expect(cell('架空の一括設定対象外児童',day)).toHaveJSProperty('indeterminate',true);
  }
  await cell(ownChild().name,7).check();await cell(ownChild().name,1).uncheck();
  await page.getByRole('button',{name:'変更をまとめて保存',exact:true}).click();
  await expect(page.getByRole('status')).toHaveText('学校日・休業日を保存しました。');await page.reload();
  await expect(cell(ownChild().name,7)).toBeChecked();await expect(cell(ownChild().name,1)).not.toBeChecked();
  await expect(cell(ownChild().name,1)).toHaveJSProperty('indeterminate',false);
  expect(await cell(ownChild().name,1).locator('..').evaluate(node=>node.closest('td').getBoundingClientRect().width)).toBeLessThan(65);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});


test('transport overview separates directions and shows matching trip details once with all children', async ({page}) => {
  fixture('transport-summary');await login(page,'staff');
  await visit(page,'/dashboard?date=2026-07-01','業務内容確認');
  const transport=page.locator('#transport-work');
  for(const [direction,time,location] of [['迎え','14:30','架空学校a'],['送り','17:30','自宅']]) {
    const section=transport.getByRole('region',{name:direction+'の送迎',exact:true});
    expect(await section.locator('thead th').allTextContents()).toEqual(['時間','場所','担当職員','児童']);
    await expect(section.locator('tbody tr')).toHaveCount(1);
    await expect(section.getByRole('cell',{name:time,exact:true})).toHaveCount(1);
    await expect(section.getByRole('cell',{name:location,exact:true})).toHaveCount(1);
    await expect(section.locator('tbody tr td').nth(2)).toContainText('架空職員a-staff');
    await expect(section.locator('tbody tr td').nth(2)).toContainText('架空職員a-leader');
    await expect(section.locator('tbody li')).toHaveCount(2);
    await expect(section).toContainText(ownChild().name);
    await expect(section).toContainText('同乗の第二児童');
  }
  await expect(transport.getByRole('button',{name:/担当変更/})).toHaveCount(0);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
});
