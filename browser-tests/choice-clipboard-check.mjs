export async function assertRegisteredChoiceClipboard(page, context) {
  await context.grantPermissions(['clipboard-read','clipboard-write'])
  const installed=await page.evaluate(()=>!!window.RegisteredChoiceClipboard)
  if(!installed) throw new Error('clipboard_handler_not_installed')
  await page.evaluate(()=>{
    const area=document.createElement('section');area.id='clipboard-probe';area.innerHTML=`
      <label>確認用の選択<select aria-label="確認用の選択"><option value="">未選択</option><option value="tel">電話</option><option value="mail">メール</option><option value="forbidden" disabled>選択不可</option></select></label>
      <label>確認用の時刻<select aria-label="確認用の時刻"><option value="09:00">09:00</option><option value="09:15">09:15</option></select></label>
      <label>確認用の複数選択<select multiple aria-label="確認用の複数選択"><option value="a">学校A</option><option value="b">学校B</option></select></label>`
    document.body.append(area);window.clipboardProbeChanges=0
    area.addEventListener('change',()=>window.clipboardProbeChanges++)
  })
  const choice=page.getByLabel('確認用の選択',{exact:true})
  await page.evaluate(()=>navigator.clipboard.writeText('メール'))
  await choice.focus();await choice.press('Control+V')
  if(await choice.inputValue()!=='mail') throw new Error('native_select_paste_failed')
  if(await page.evaluate(()=>window.clipboardProbeChanges)!==1) throw new Error('change_must_fire_exactly_once')
  await choice.press('Control+C')
  if(await page.evaluate(()=>navigator.clipboard.readText())!=='メール') throw new Error('copy_must_use_display_label')
  for(const value of ['未登録','選択不可','電話\nメール']) {
    let warning='';page.once('dialog',async d=>{warning=d.message();await d.accept()})
    await page.evaluate(text=>navigator.clipboard.writeText(text),value)
    await choice.focus();await choice.press('Control+V')
    if(!warning || await choice.inputValue()!=='mail') throw new Error('invalid_paste_changed_select')
  }
  const time=page.getByLabel('確認用の時刻',{exact:true})
  await page.evaluate(()=>navigator.clipboard.writeText('9:15'))
  await time.focus();await time.press('Control+V')
  if(await time.inputValue()!=='09:15') throw new Error('time_normalization_failed')
  const multiple=page.getByLabel('確認用の複数選択',{exact:true})
  await page.evaluate(()=>navigator.clipboard.writeText('学校A\n学校B'))
  await multiple.focus();await multiple.press('Control+V')
  if((await multiple.evaluate(el=>[...el.selectedOptions].map(o=>o.value))).join(',')!=='a,b') throw new Error('multi_select_paste_failed')
  await page.locator('#clipboard-probe').evaluate(el=>el.remove())
}
