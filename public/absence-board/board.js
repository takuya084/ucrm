// Shared by both apps. Keep native labels, escaped content and an explicit save per record.
const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))
const methods = {tel:'電話',line:'LINE',app:'アプリ',in_person:'対面',email:'メール',other:'その他'}
const freeze=root=>{const controls=[...root.querySelectorAll('input,select,textarea,button')].map(el=>[el,el.disabled]);controls.forEach(([el])=>el.disabled=true);return ()=>controls.forEach(([el,disabled])=>{if(el.isConnected)el.disabled=disabled})}
const blanks = () => ({is_absent:true,contacted_at:'',reason:'',contact_method:'',caller:'',receiver_staff_id:null,consultant_staff_id:null,consultant_role:'',child_condition:'',support_content:'',next_date:'',was_planned:null,sudden:null,service_available:null,consultation_done:null,special_case:false,claim_confirmed:false})
export async function request(url, method='GET', payload) {
  const xsrf=document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN='))?.slice(11)
  const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/json',...(xsrf?{'X-XSRF-TOKEN':decodeURIComponent(xsrf)}:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content ?? ''})},...(payload?{body:JSON.stringify(payload)}:{})})
  const data=await response.json().catch(()=>null)
  if (!response.ok || (method!=='GET' && data?.ok!==true)) {
    const messages=Object.values(data?.errors || {}).flat().join(' / ')
    throw new Error(response.status===419?'認証情報が期限切れです。入力内容を控え、再読み込みしてください。':messages || '通信結果を確認できません。入力内容を控え、最新の一覧で保存結果を確認してください。')
  }
  return data
}
export function mountAbsences(root, options) {
  const drafts=new Map()
  let calendarDirty=false
  let data, dirty=false, busy=false, disposed=false, savedMessage=''
  const name=id=>data.children.find(c=>c.id===id)?.name || '登録済み児童'
  const optionsHtml=(list,value,empty='選択してください')=>`<option value="">${empty}</option>`+list.map(p=>`<option value="${escape(p.value ?? p.id)}" ${String(p.value ?? p.id)===String(value)?'selected':''}>${escape(p.label ?? p.name)}</option>`).join('')
  const input=(key,label,value,type='text')=>`<label>${label}<input name="${key}" type="${type}" value="${escape(value)}" ${type==='text'?'maxlength="2000"':''}></label>`
  const bool=(key,label,value)=>`<label>${label}<select name="${key}">${optionsHtml([{value:'true',label:'はい'},{value:'false',label:'いいえ'}],value===null?'':String(value),'未確認')}</select></label>`
  function collectForm(el) {
    const row=data.rows.find(r=>r.child_id===+el.dataset.child),payload={...blanks(),...row.payload}
    for(const element of el.elements){if(!element.name)continue;let value=element.type==='checkbox'?element.checked:element.value.trim();if(['receiver_staff_id','consultant_staff_id'].includes(element.name))value=value?+value:null;if(['was_planned','sudden','service_available','consultation_done'].includes(element.name))value=value===''?null:value==='true';payload[element.name]=value || value===false?value:null}
    delete payload.receiver_name;delete payload.consultant_name
    return payload
  }
  function form(row) {
    const v={...blanks(),...row.payload};const assessment=row.assessment
    return `<details class="ab-record" ${row.revision===0?'open':''}><summary><strong>${escape(name(row.child_id))}</strong><span>${escape(assessment?.status || '未保存')}</span><span>${escape(v.reason || '理由未記録')}</span></summary><form data-child="${row.child_id}" data-revision="${row.revision}">
    <div class="ab-fields">${input('contacted_at','連絡日時（日本時間）',v.contacted_at,'datetime-local')}${input('reason','欠席理由',v.reason)}
    <label>連絡手段<select name="contact_method">${optionsHtml(Object.entries(methods).map(([value,label])=>({value,label})),v.contact_method)}</select></label>
    ${input('caller','連絡者（母・父など）',v.caller)}
    <label>受付職員<select name="receiver_staff_id">${optionsHtml(data.staff,v.receiver_staff_id)}</select></label>
    <label>相談援助を行った職員<select name="consultant_staff_id">${optionsHtml(data.staff,v.consultant_staff_id)}</select></label>
    ${input('consultant_role','相談職員の職名',v.consultant_role)}${input('next_date','次回利用予定日',v.next_date,'date')}
    <label class="ab-wide">児童の状況<textarea name="child_condition" maxlength="5000">${escape(v.child_condition)}</textarea></label>
    <label class="ab-wide">相談援助の内容・経過<textarea name="support_content" maxlength="5000">${escape(v.support_content)}</textarea></label>
    </div><details class="ab-checks"><summary>加算の条件を確認</summary><div class="ab-fields">${bool('was_planned','事前に利用予定があった',v.was_planned)}${bool('sudden','急病等の予期しない欠席',v.sudden)}${bool('service_available','事業所に受入体制があった',v.service_available)}${bool('consultation_done','状況確認・相談援助を行った',v.consultation_done)}</div>
    <label class="ab-check"><input name="special_case" type="checkbox" ${v.special_case?'checked':''}>重症心身障害児の特例等の個別確認が必要</label>
    ${data.can_confirm?`<label class="ab-check"><input name="claim_confirmed" type="checkbox" ${v.claim_confirmed?'checked':''}>通常の算定条件を確認済み（請求の自動送信はしません）</label>`:''}</details>
    <label class="ab-check"><input name="is_absent" type="checkbox" ${v.is_absent?'checked':''}>欠席として記録する（解除すると欠席取消。記録は保持）</label>
    ${assessment?`<div class="ab-assessment"><strong>${escape(assessment.status)}</strong> ／ 日付条件：${escape(assessment.timing)}${assessment.earliest_contact_date?`（${escape(assessment.earliest_contact_date)}〜${escape(row.date)}）`:''}<p>同月の他の確認済み ${assessment.other_confirmed}回 ／ 通常上限4回</p>${assessment.missing.length?`<p>不足：${escape(assessment.missing.join('、'))}</p>`:''}${assessment.reasons.map(r=>`<p>${escape(r)}</p>`).join('')}</div>`:''}
    <p data-error role="alert"></p><button type="submit">この記録を保存</button></form></details>`
  }
  function render() {
    if(disposed)return
    root.innerHTML=`<div class="ab-board"><div class="ab-toolbar"><strong>欠席連絡・相談記録</strong><button type="button" data-refresh>最新の一覧を確認</button></div><p data-global role="status">${escape(savedMessage)}</p>
    <div class="ab-toolbar"><label>児童<select data-child-select>${optionsHtml(data.children.filter(c=>!data.rows.some(r=>r.child_id===c.id)),null)}</select></label><button type="button" data-add>欠席記録を追加</button></div>
    ${data.rows.length?data.rows.map(form).join(''):'<p>この日の欠席記録はありません。</p>'}
    <details class="ab-calendar"><summary>営業日を設定・確認</summary><form data-calendar><p>定休日・臨時休業日を確認して保存してください。判定にはここの営業日を使用します。過去日の確認では当時の営業日に合わせてください。</p><div class="ab-weekdays">${['月','火','水','木','金','土','日'].map((day,i)=>`<label><input type="checkbox" name="weekday" value="${i+1}" ${data.calendar?.weekdays?.includes(i+1)?'checked':''} ${!data.can_confirm?'disabled':''}>${day}</label>`).join('')}</div><label>臨時休業日（1行に1日、YYYY-MM-DD）<textarea name="closed_dates" ${!data.can_confirm?'disabled':''}>${escape(data.calendar?.closed_dates?.join('\n') || '')}</textarea></label>${data.can_confirm?'<button type="submit">営業日を確認して保存</button>':''}<p data-error role="alert"></p></form></details>
    <details class="ab-guide"><summary>判定の見方・記録項目</summary><p>前2営業日〜当日の連絡に加え、急病等・事前の予定・受入体制・状況確認と相談援助の記録を確認します。「算定候補」は自動請求ではありません。請求前に出欠・月回数・受給者証・実績記録票等を確認してください。次回予定が未定なら記録不足として残し、決まり次第追記できます。</p><p>同じ相談日時の複数日算定は防ぎます。別の日時であっても、同じ1回の相談を複数日に転記しないでください。通常月4回を超える特例は自動で可と判定しません。</p><p>営業日設定は両アプリ共通の欠席判定用です。予約受付の営業日設定は各事業所設定で管理します。</p><a href="https://www.cfa.go.jp/policies/shougaijishien/shisaku/r8hoshukaitei" target="_blank" rel="noopener noreferrer">こども家庭庁・令和8年度報酬改定</a></details></div>`
    root.querySelector('[data-refresh]').onclick=()=>load(true)
    root.querySelector('[data-add]').onclick=()=>{const id=+root.querySelector('[data-child-select]').value;if(!id)return;const row={child_id:id,date:options.date,revision:0,payload:blanks()};data.rows.push(row);drafts.set(id,row);dirty=true;render()}
    root.querySelectorAll('form[data-child]').forEach(el=>{
      el.oninput=()=>{dirty=true;const row=data.rows.find(r=>r.child_id===+el.dataset.child);row.payload=collectForm(el);drafts.set(row.child_id,row)}
      el.onsubmit=async event=>{
        event.preventDefault();if(busy)return;if(calendarDirty){el.querySelector('[data-error]').textContent='先に営業日設定を保存するか、最新の一覧で設定の入力を取り消してください。';return}
        const row=data.rows.find(r=>r.child_id===+el.dataset.child),payload=collectForm(el)
        payload.claim_confirmed=data.can_confirm?payload.claim_confirmed:false
        if(!payload.is_absent)payload.claim_confirmed=false
        // Keep other open drafts before refreshing the saved record.
        busy=true;const thaw=freeze(root);const button=el.querySelector('button[type=submit]');button.disabled=true
        try{await options.save({date:options.date,child_id:row.child_id,revision:row.revision,payload});drafts.delete(row.child_id);dirty=drafts.size>0;savedMessage='保存しました。';await load(false,true)}catch(e){el.querySelector('[data-error]').textContent=e.message;dirty=true}finally{busy=false;thaw();if(button.isConnected)button.disabled=false}
      }
    })
    root.querySelector('[data-calendar]').oninput=()=>{dirty=true;calendarDirty=true}
    root.querySelector('[data-calendar]').onsubmit=async event=>{event.preventDefault();if(busy)return;const el=event.currentTarget;busy=true;const thaw=freeze(root)
      try {await options.calendar({revision:data.calendar?.revision ?? 0,weekdays:[...el.querySelectorAll('[name=weekday]:checked')].map(n=>+n.value),closed_dates:el.elements.closed_dates.value.split(/\s+/).filter(Boolean),confirmed:true});calendarDirty=false;dirty=drafts.size>0;savedMessage='営業日を保存しました。';await load(false,true)}catch(e){el.querySelector('[data-error]').textContent=e.message}finally{busy=false;thaw()}}
  }
  async function load(confirmDirty=false,preserveDrafts=false) {if(confirmDirty && dirty && !confirm('未保存の入力を破棄して最新の一覧を読み込みますか？'))return;try{const fresh=await options.load();if(preserveDrafts){for(const draft of drafts.values()){const i=fresh.rows.findIndex(row=>row.child_id===draft.child_id);if(i<0)fresh.rows.push(draft);else fresh.rows[i]=draft}}else {drafts.clear();calendarDirty=false}data=fresh;dirty=drafts.size>0;render()}catch(e){if(!data)root.innerHTML='<p role="alert"></p>';(root.querySelector('[data-global]')||root.querySelector('[role=alert]')).textContent=e.message}}
  const before=event=>{if(dirty){event.preventDefault();event.returnValue=''}};window.addEventListener('beforeunload',before);load()
  return {destroy(){disposed=true;window.removeEventListener('beforeunload',before)},hasChanges:()=>dirty}
}
if(typeof document!=='undefined')document.querySelectorAll('[data-absence-board]').forEach(root=>{const business_id=+root.dataset.business,date=root.dataset.date;mountAbsences(root,{date,load:()=>request(root.dataset.url+'?'+new URLSearchParams({date,business_id})),save:p=>request(root.dataset.url,'PUT',{...p,business_id}),calendar:p=>request(root.dataset.calendar,'PUT',{...p,business_id})})})
