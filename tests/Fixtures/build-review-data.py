"""Synthetic UI review fixtures. No real names, addresses or deliverable contacts."""
from collections import Counter
from datetime import date, datetime, timedelta, timezone
import calendar
import json
import sys
import uuid

VERSION = 'review-samples-20261128-v2'
PASSWORD = 'Demo-local-2026!'

def ref(name): return {'$ref': name}
def month(day, offset=0):
    serial = day.year * 12 + day.month - 1 + offset
    return date(serial // 12, serial % 12 + 1, 1)
def days(first):
    return [first + timedelta(days=i) for i in range(calendar.monthrange(first.year, first.month)[1])]
def build(today=None):
    today = today or datetime.now(timezone(timedelta(hours=9))).date()
    first, previous, following = month(today), month(today, -1), month(today, 1)
    closure=following+timedelta(days=22)
    while closure.weekday()==6:closure+=timedelta(days=1)
    fmt = lambda d: d.isoformat()
    stamp = lambda d: fmt(d) + ' 10:00:00'
    ops = {'p': [], 'u': []}
    def put(kind, key, table, **values):
        ops[kind].append({'key': key, 'table': table, 'values': values})
        return ref(key)
    def add(key, table, **values): return put('u', key, table, **values)
    staff = [ref('admin_staff')]
    staff_names = ['【テスト】管理者', '【架空】若葉 先生', '【架空】星野 先生', '【架空】小川 先生', '【架空】虹野 先生', '【架空】春風 先生', '【架空】月見 先生', '【架空】花園 先生', '【架空】森野 運転手', '【架空】朝日 先生（休職）']
    for i in range(1, 10):
        name, email = staff_names[i], f'staff-{i:02d}@example.test'
        role = 'leader' if i == 1 else ('driver' if i == 8 else 'staff')
        user = add(f'staffuser{i}', 'users', name=name, email=email, password={'$password': True}, email_verified_at=stamp(today))
        staff.append(add(f'staff{i}', 'staff', user_id=user, facility_id=ref('facility'), name=name, role=role,
            is_active=i != 9, employment_type=['full_time', 'part_time', 'contract'][i % 3], display_order=i,
            monthly_salary=260000 if i % 3 == 0 else None, hourly_wage=1300 + 50 * i if i % 3 != 0 else None,
            joined_at=fmt(month(today, -12))))
        put('p', f'teacher{i}', 'users', id=2000+i, name=name, email=email, password={'$password': True},
            person_type='teacher', access_role='member', business_id=ref('business'), email_verified_at=stamp(today))
    for i, qualifications in enumerate([['jidou_shidouin'], ['hoikushi'], ['jidou_shidouin'], ['pt'], ['ot'], ['st'], ['hoikushi'], [], ['sougeikanou'], []]):
        for q in qualifications: add(f'qualification{i}-{q}', 'staff_qualifications', staff_id=staff[i], qualification=q)
    schools_p, schools_u = [], []
    for i, (name, kind) in enumerate([('【架空】第一小学校','elementary'), ('【架空】第二中学校','junior_high'), ('【架空】ひかり特別支援学校','special_needs'), ('【架空】わかば園','other')]):
        schools_p.append(put('p', f'school{i}', 'schools', business_id=ref('business'), name=name))
        schools_u.append(add(f'school{i}', 'schools', facility_id=ref('facility'), name=name, school_type=kind,
            end_time_regular='14:30' if i != 1 else '15:30', end_time_early='13:00', memo='【架空】短縮授業・学校選択の確認用'))
    for i, name in enumerate(['【架空】東コース','【架空】西コース','【架空】徒歩・保護者対応']):
        put('p', f'direction{i}', 'directions', business_id=ref('business'), name=name)
    put('p','closure','holidays',business_id=ref('business'),date=fmt(closure))
    names = ['青空 はると','若葉 ひまり','星野 そうた','小川 ゆい','虹野 みなと','春風 あおい','月見 りく','花園 つむぎ','森野 かえで','朝日 いおり','水辺 なぎ','雪原 こはる','青空 はると','小鳥 ひなた（長い氏名の表示確認用）','菜の花 すず','風見 いつき','日向 そら','夕空 ほのか']
    guardians, certificates = {}, {}
    for i, short in enumerate(names):
        cid, email = 1001+i, f'child-{i+1:02d}@example.test'
        school = 3 if i in [11, 12] else i % 3
        name = '【架空】' + short
        put('p', f'child{i}', 'users', id=cid, name=name, email=email, password={'$password':True}, person_type='child',
            access_role='member', business_id=ref('business'), school_id=schools_p[school],
            school_name=['【架空】第一小学校','【架空】第二中学校','【架空】ひかり特別支援学校','【架空】わかば園'][school],
            direction_id=ref(f'direction{i%3}'), email_verified_at=stamp(today))
        status = 'active' if i < 14 else ('suspended' if i < 16 else 'ended')
        add(f'child{i}', 'children', id=cid, facility_id=ref('facility'), school_id=schools_u[school], name=name,
            name_kana=f'カクウ ジドウ{i+1:02d}', gender=['male','female','other',None][i%4],
            birthdate=f'{today.year-([7+i%5,13+i%3,8+i%5,4+i%2][school])}-04-02', grade=(['小'+str(1+i%5),'中'+str(1+i%3),'小'+str(2+i%5),'年中' if i%2==0 else '年長'][school]),
            contract_status=status, contract_start_date=fmt(month(today,-12)), contract_end_date=fmt(previous-timedelta(days=1)) if status=='ended' else None,
            yoyaku_user_id=cid, pickup_required=i%4!=3, pickup_area=['東','西','保護者'][i%3],
            pickup_address='【架空】サンプル市テスト町1-1' if i%4!=3 else None,
            allergy_note=['【架空】卵・乳製品。活動前に確認。',None,'【架空】小麦。保護者への確認事項あり。',''][i%4],
            care_note='【架空】見通しを短い言葉と写真で伝えます。\n静かな場所で休憩できます。' if i%2==0 else None,
            memo='【架空・確認用】'+['通常利用','アレルギーあり','同姓同名の区別','送迎なし','利用上限少なめ','長文と改行の表示'][i%6])
        if i == 1: guardians[i] = guardians[0]  # Siblings share one guardian.
        else:
            guardians[i] = add(f'guardian{i}', 'guardians', name=f'【架空】保護者{i+1:02d}', relationship=['mother','father','grandmother','other'][i%4],
                email=email, tel_primary=f'000-0000-{i+1:04d}', preferred_contact=['email','tel','in_person','line'][i%4],
                address='【架空】サンプル市テスト町1-1', postcode='000-0000', memo='【架空】連絡先・住所はダミーです。')
        add(f'family{i}', 'child_guardian_relations', child_id=cid, guardian_id=guardians[i], is_primary=True, is_emergency_contact=True, priority_order=1)
        if i in [0, 2, 5]:
            extra=add(f'guardian_extra{i}','guardians',name=f'【架空】第二連絡先{i+1:02d}',relationship='father',email=f'guardian-extra-{i}@example.test',preferred_contact='email')
            add(f'family_extra{i}','child_guardian_relations',child_id=cid,guardian_id=extra,is_primary=False,is_emergency_contact=True,priority_order=2)
        if i != 13:  # No certificate is a genuine review case, not a fake valid certificate.
            start, end = month(today,-6), month(today,6)-timedelta(days=1)
            if i==10: start=first
            if i==8: end=today+timedelta(days=10)
            if i==9: end=today-timedelta(days=1)
            cert_status='expired' if i==9 else ('pending' if i==12 else 'active')
            certificates[i]=add(f'certificate{i}','recipient_certificates',child_id=cid,certificate_number=f'990000{i+1:04d}',municipality='【架空】サンプル市',
                valid_from=fmt(start),valid_to=fmt(end),monthly_limit=[23,10,5][i%3],copayment_rate=10,copayment_cap_monthly=[0,4600,37200][i%3],
                is_cap_management_target=i in [2,5,8],cap_managing_facility_id=ref('facility') if i in [2,5,8] else None,
                service_type='jidou' if school==3 else 'houday',status=cert_status,issue_date=fmt(start),is_free_of_charge=i%5==0,guardian_name=f'【架空】保護者{i+1:02d}')
            add(f'contract{i}','contracts',child_id=cid,facility_id=ref('facility'),recipient_certificate_id=certificates[i],contracted_amount=[23,10,5][i%3],contract_start_date=fmt(start),record_number='01')
            if i==10:
                add('certificate10_old','recipient_certificates',child_id=cid,certificate_number='9900000011',valid_from=fmt(month(today,-6)),valid_to=fmt(first-timedelta(days=1)),monthly_limit=23,service_type='jidou',status='expired')
        if i<14:
            for j,dow in enumerate(['mon','tue','wed','thu','fri','sat']):
                if (i+j)%3!=0:
                    add(f'schedule{i}-{dow}','child_schedules',child_id=cid,day_of_week=dow,start_date=fmt(previous),status='regular',pickup_required=i%4!=3)
    labels=[('早番','09:00',8),('遅番','10:00',8),('短時間','13:00',5),('送迎','13:30',4),('休み',None,0),('有給',None,0),('研修','09:30',7)]
    for i,(name,time,hours) in enumerate(labels):
        add(f'label{i}','shift_labels',facility_id=ref('facility'),name=name,is_off=time is None,work_hours=hours,display_order=i)
    for i,s in enumerate(staff):
        for j,dow in enumerate(['mon','tue','wed','thu','fri','sat','sun']):
            label=4 if j==6 or i==9 or (i+j)%7==0 else (3 if i==8 else i%3)
            add(f'work{i}-{j}','staff_work_patterns',facility_id=ref('facility'),staff_id=s,day_of_week=dow,start_time=labels[label][1],work_type=labels[label][0])
    special_dates={fmt(first+timedelta(days=x-1)): text for x,text in [(5,'定員超過（11名）の表示'),(12,'職員不足の警告'),(15,'有給・短時間勤務'),(20,'児発管・有資格者不足'),(25,'研修・学校休業日')]}
    for m in [previous,first,following]:
        ym=m.strftime('%Y-%m')
        ms=add('shift'+ym,'monthly_shifts',facility_id=ref('facility'),year=m.year,month=m.month,status='confirmed' if m<first else 'draft',created_by=ref('admin_staff'))
        for d in days(m):
            for i,s in enumerate(staff):
                label=4 if d.weekday()==6 or i==9 or (i+d.weekday())%7==0 else (3 if i==8 else i%3)
                if d.day==12 and i>=1 or d.day==20 and i<=6:label=4
                if d.day==15 and i in [0,3]:label=5
                if d.day==25 and i in [2,4]:label=6
                time=labels[label][1]
                if d.day==18 and i==7:time=None # unset time is allowed, and deliberately visible.
                add(f'entry{ym}-{d.day}-{i}','shift_entries',monthly_shift_id=ms,staff_id=s,date=fmt(d),start_time=time,work_type=labels[label][0],note='【架空】時刻未設定の確認' if d.day==18 and i==7 else None)
            if d.day in [5,12,15,20,25]:add(f'shiftnote{ym}-{d.day}','shift_day_notes',monthly_shift_id=ms,date=fmt(d),note='【架空】'+special_dates.get(fmt(first.replace(day=d.day)),'確認用'))
    programs=[]
    for i,(name,category) in enumerate([('バランスディスク','physical'),('トランポリン','physical'),('ボール運び','physical'),('順番ゲーム','social'),('宿題・文字','cognitive'),('おやつの準備','life_skills')]):
        program=add(f'program{i}','programs',facility_id=ref('facility'),name='【架空】'+name,category=category,duration_minutes=15,is_active=True,description='【架空】短い説明と段階別の達成を確認します。')
        programs.append(program)
        for level in range(1,4):
            item=add(f'item{i}-{level}','program_items',program_id=program,name=['補助を受けて取り組む','一人で取り組む','友達と交代して取り組む'][level-1],difficulty_order=level)
            for ci in range(4):add(f'progress{i}-{level}-{ci}','child_program_progress',child_id=1001+ci,program_item_id=item,status='mastered' if level==1 else 'practicing',achieved_at=fmt(today-timedelta(days=7)) if level==1 else None)
    add('inactive-program','programs',facility_id=ref('facility'),name='【架空】旧プログラム（停止）',category='other',duration_minutes=15,is_active=False)
    all_dates=[d for m in [previous,first,following] for d in days(m) if d.weekday()!=6 and d!=closure]
    usages={};last_attended={};notes=[];cancelled=[]
    for di,d in enumerate(all_dates):
        day=fmt(d);ym=d.strftime('%Y-%m');base_count=11 if d.day==5 else (10 if d.day==10 else 8)
        selection=[(di+i)%14 for i in range(base_count)]
        if d==today:selection=list(range(10))
        add('slot'+day,'available_slots',facility_id=ref('facility'),date=day,day_of_week=['mon','tue','wed','thu','fri','sat'][d.weekday()],total_capacity=10,booked_count=sum(d>today or d.day==5 or d==today and i<4 or (i+di)%8!=7 for i in selection),notes='【架空】'+('定員超過の警告確認' if len(selection)>10 else '空き状況の確認'))
        add('activity'+day,'daily_activity_plans',facility_id=ref('facility'),program_id=programs[di%6],staff_id=staff[di%8],activity_date=day,category=['event','support','exercise','learning'][di%4],title='【架空】'+['運動あそび','制作活動','協力ゲーム'][di%3],starts_at='14:30',materials='【架空】ボール、カード、筆記用具')
        for i in range(14):
            # Deliberately leave one child's school-day setting unset on most dates.
            if i!=13 and not (i==12 and d.day%2==0):
                add(f'schoolday{day}-{i}','child_school_days',facility_id=ref('facility'),child_id=1001+i,date=day,is_school_day=d.weekday()<5 and d.day!=25,updated_by=ref('admin_staff'))
        for offset,i in enumerate(selection):
            cid=1001+i;school_day=d.weekday()<5 and d.day!=25
            pickup='14:30' if school_day else '09:30';dropoff='17:30' if school_day else '15:30'
            mode=(i+di)%5
            pt=None if mode in [2,3] else pickup;dt=None if mode in [1,3] else dropoff
            # mode 4 deliberately leaves the second driver unset; all values remain valid.
            status=['attended','attended','attended','attended','attended','absent_notice','absent','cancel'][(i+di)%8]
            if d==today and i<4 or d.day==5:status='attended'
            if d<=today and status=='cancel':cancelled.append({'user_id':cid,'date':day})
            if d>today or status!='cancel':put('p',f'booking{day}-{i}','yoyakus',business_id=ref('business'),user_id=cid,date=day,pickup_time=pt,dropoff_time=dt,
                pickup_location='school' if school_day else 'home',dropoff_location='parents' if mode==3 else 'home',
                pickup_staff_id=None if mode==4 or pt is None else 2008,dropoff_staff_id=None if dt is None else 2007,
                pickup_assistant_staff_id=2006 if mode==0 else None,dropoff_assistant_staff_id=2005 if mode==0 else None,transport_revision=1)
            put('p',f'revision{day}-{i}','reservation_revisions',user_id=cid,date=day,revision=str(uuid.uuid5(uuid.NAMESPACE_URL,VERSION+day+str(cid))))
            if d>today or status!='cancel':
                location=['【架空】第一小学校','【架空】第二中学校','【架空】ひかり特別支援学校','【架空】わかば園'][3 if i in [11,12] else i%3] if school_day else '自宅'
                if pt is not None and mode!=4:
                    add(f'pickup{day}-{i}','daily_transport_assignments',facility_id=ref('facility'),child_id=cid,staff_id=staff[8],assistant_staff_id=staff[6] if mode==0 else None,service_date=day,direction='pickup',scheduled_time=pt,location=location)
                if dt is not None:
                    add(f'dropoff{day}-{i}','daily_transport_assignments',facility_id=ref('facility'),child_id=cid,staff_id=staff[7],assistant_staff_id=staff[5] if mode==0 else None,service_date=day,direction='dropoff',scheduled_time=dt,location='自宅')

            if d>today:continue # Future reservations are not fabricated attendance.
            status=['attended','attended','attended','attended','attended','absent_notice','absent','cancel'][(i+di)%8]
            # Keep today's first four rows useful for a walkthrough.
            if d==today and i<4 or d.day==5:status='attended'
            attended=status=='attended'
            ur=add(f'usage{day}-{i}','usage_records',facility_id=ref('facility'),child_id=cid,staff_id=staff[1],date=day,status=status,
                check_in_time=pickup if attended else None,check_out_time=(None if d==today and i==2 else dropoff) if attended else None,
                pickup_done=attended and pt is not None,dropoff_done=attended and dt is not None and not(d==today and i==2),
                billing_target=i not in [9,12,13],is_school_day=school_day,service_type=None,
                absent_reason='【架空】発熱のため家庭で休養' if status=='absent_notice' else None,memo='【架空】'+('来所中・退所未入力' if d==today and i==2 else '画面確認用の利用実績'))
            usages[(day,i)]=ur
            if status=='absent_notice':add(f'absence-support{day}-{i}','absence_supports',usage_record_id=ur,staff_id=staff[1],contacted_at=day+' 08:30:00',contact_method='tel',support_content='【架空】体調と家庭での過ごし方を確認し、次回利用を調整しました。')
            if attended:last_attended[i]=(day,ur)
            if not attended or d<today-timedelta(days=8):continue
            support=add(f'support{day}-{i}','support_records',child_id=cid,usage_record_id=ur,staff_id=staff[2],date=day,condition=['good','normal','poor'][i%3],
                behavior_note='【架空】友達と順番を守って活動しました。',achievement_note='【架空】最後まで取り組めました。',
                challenge_note='【内部専用】保護者向け本文に混ざらないことを確認。',care_note='【内部専用】静かな場所で休憩します。',next_action='【架空】次回も見通しを伝えます。')
            add(f'participation{day}-{i}','support_record_programs',support_record_id=support,program_id=programs[i%6],duration_minutes=15,memo='【内部専用】活動の詳細メモ')
            if i%6==5:continue # Uncreated contact note.
            published=i%4 in [0,2]; nid=300000+di*20+i
            add(f'note{day}-{i}','contact_notes',id=nid,facility_id=ref('facility'),child_id=cid,support_record_id=support,staff_id=staff[2],date=day,
                meal_note='【架空】おやつを食べました。',health_note='【架空】体調は安定していました。',guardian_message='【架空】友達と協力してボール運びを楽しみました。\n自分から挨拶できました。',
                five_domain_tags=['motor_sensory','social_relations'],goal_progress=['achieved','partial','difficult'][i%3],status='published' if published else 'draft',
                published_at=day+' 00:00:00' if published else None,published_by=staff[2] if published else None,
                home_temperature='36.5' if i%3==0 else None,home_sleep='21:00〜06:30' if i%3==0 else None,
                guardian_comment='【架空】昨夜はよく眠れました。' if i%3==0 else None,guardian_submitted_at=day+' 08:00:00' if i%3==0 else None,
                guardian_source_revision=None)
            if published:notes.append(nid)
    for m in [previous,first,following]:add('calendar'+m.strftime('%Y-%m'),'school_calendar_months',facility_id=ref('facility'),month=m.strftime('%Y-%m'),revision=1)
    # Same absence schema in both apps. Staff IDs intentionally refer to each app's own staff.
    for kind,tenant in [('p',ref('business')),('u',ref('facility'))]:
        put(kind,'absence-calendar','absence_calendars',tenant_id=tenant,revision=1,payload={'weekdays':[1,2,3,4,5,6],'closed_dates':[fmt(closure)],'confirmed':True})
        for i in range(6):
            d=today-timedelta(days=i)
            while d.weekday()==6:d-=timedelta(days=1)
            payload={'is_absent':i!=5,'contacted_at':fmt(d)+'T08:30','reason':'【架空】体調不良','contact_method':'tel','caller':'【架空】保護者',
                'receiver_staff_id':2001 if kind=='p' else ref('staff1'),'consultant_staff_id':2001 if kind=='p' else ref('staff1'),
                'receiver_staff_name':staff_names[1],'consultant_staff_name':staff_names[1],'consultant_role':'児童指導員',
                'child_condition':'【架空】発熱。自宅で休養中。','support_content':'【架空】次回利用の目安と家庭での過ごし方を相談。','next_date':fmt(today+timedelta(days=2)),
                'was_planned':True,'sudden':i!=3,'service_available':True,'consultation_done':i!=2,'claim_confirmed':False,'special_case':i==4}
            if i==1:payload['support_content']=''
            notice=put(kind,f'absence{i}','absence_notices',tenant_id=tenant,child_id=1001+i,date=fmt(d),revision=1,payload=payload)
            put(kind,f'absence-version{i}','absence_notice_versions',absence_notice_id=notice,actor_id=ref('admin_user'),revision=1,payload=payload)
    for i in range(10):
        cid=1001+i;done=i%3
        plan=add(f'plan{i}','support_plans',child_id=cid,staff_id=staff[1],plan_date=fmt(today-timedelta(days=20)),valid_from=fmt(first),valid_to=fmt(month(today,6)-timedelta(days=1)),
            long_term_goal='【架空】安心して友達と活動に参加します。',short_term_goal='【架空】順番を待って交代できます。',support_policy='【架空】見通しを伝え、小さな成功を重ねます。',
            program_content='【架空】運動・生活・やり取りの練習',planned_start_time='14:00',planned_end_time='17:00',planned_duration_minutes=180,
            five_domains={'health_life':'【架空】生活の見通しを持ちます。','motor_sensory':'【架空】バランスと体幹を練習します。','cognition_behavior':'【架空】短い約束を守ります。','language_communication':'【架空】自分の気持ちを言葉で伝えます。','social_relations':'【架空】友達と順番に取り組みます。'},status=['draft','approved','delivered'][done],
            approved_by=staff[1] if done else None,approved_at=stamp(today-timedelta(days=15)) if done else None,guardian_agreement=done==2,guardian_agreement_date=fmt(today-timedelta(days=10)) if done==2 else None)
        add(f'assessment{i}','assessments',child_id=cid,staff_id=staff[1],assessed_at=fmt(today-timedelta(days=25)),physical_condition='【架空】運動に意欲があります。',child_intention='【架空】友達と遊びたい。',guardian_intention='【架空】落ち着いて過ごしてほしい。',five_domains={'motor_sensory':'【架空】体幹とバランスを練習'},notes='【架空】アセスメント確認用')
        add(f'monitoring{i}','monitoring_records',child_id=cid,staff_id=staff[1],monitoring_date=fmt(today-timedelta(days=3)),period_from=fmt(month(today,-3)),period_to=fmt(today),support_summary='【架空】活動に参加できる時間が延びました。',strengths='【架空】好きな運動に集中できます。',challenges='【架空】気持ちの切り替えを練習中です。',next_review_date=fmt(today+timedelta(days=30 if i%2 else 7)))
        if done:
            add(f'meeting{i}','support_plan_meetings',support_plan_id=plan,held_at=stamp(today-timedelta(days=16)),attendees=[staff_names[1]+'（児発管）',staff_names[2]+'（児童指導員）'],minutes='【架空】本人・保護者の希望と5領域の支援を確認しました。')
        if done==2:add(f'consent{i}','support_plan_consents',support_plan_id=plan,guardian_id=guardians[i],consented_at=stamp(today-timedelta(days=10)),method='paper',delivered_at=stamp(today-timedelta(days=9)))
    # Clearly fictional amounts/codes are solely for screen-state review, not valid claims.
    code=add('demo-code','service_code_masters',revision_date=fmt(month(today,-12)),service_type='houday',service_code='999901',service_name='【架空・画面確認専用】基本サービス（正式請求不可）',unit_count=100,unit_type='per_day',category='base',conditions={'demo_only':True},valid_from=fmt(month(today,-12)))
    external=add('external','external_facilities',facility_id=ref('facility'),service_type='houday',facility_number='9999999999',name='【架空】連携先サンプル事業所',notes='【架空】送信・実請求は行いません。')
    for i in [2,5,8]:add(f'external-certificate{i}','certificate_external_facility',recipient_certificate_id=certificates[i],external_facility_id=external)
    for mi,m in enumerate([month(today,-3),month(today,-2),previous,first]):
        ym=m.strftime('%Y-%m');period=add('billperiod'+ym,'billing_periods',facility_id=ref('facility'),year_month=ym,status=['completed','submitted','error','draft'][mi],notes='【架空・画面確認専用】金額・コードは仮数値。請求計算の正しさを示すものではありません。')
        for i in range(6):
            amount=1000*(i+1);share=min(amount//10,[0,4600,37200][i%3]);ds=['draft','confirmed','submitted','returned','corrected','draft'][i]
            detail=add(f'detail{ym}-{i}','billing_details',billing_period_id=period,child_id=1001+i,recipient_certificate_id=certificates[i],service_type='houday',total_days=i+1,total_units=100*(i+1),unit_price_yen='10.00',total_amount=amount,insurance_amount=amount-share,copayment_amount=share,copayment_cap=[0,4600,37200][i%3],copayment_cap_applied=share,status=ds)
            add(f'billline{ym}-{i}','billing_detail_lines',billing_detail_id=detail,service_code_master_id=code,service_code='999901',service_name='【架空・画面確認専用】基本サービス',count=i+1,units_per_count=100,total_units=100*(i+1))
            historical=next((op for op in ops['u'] if op['table']=='usage_records' and op['values']['child_id']==1001+i and op['values']['date'].startswith(ym) and op['values']['status']=='attended'),None)
            if historical:
                values=historical['values']
                add(f'dailyservice{ym}-{i}','daily_service_records',usage_record_id=ref(historical['key']),billing_detail_id=detail,service_code_master_id=code,service_code='999901',units=100,start_time=values['check_in_time'],end_time=values['check_out_time'],is_pickup=values['pickup_done'],is_dropoff=values['dropoff_done'],is_extension=False,memo='【架空・画面確認専用】日別算定欄の仮数値です。')

            pay=['unpaid','paid','partial','overdue'][i%4];total=share+200
            add(f'invoice{ym}-{i}','guardian_invoices',billing_detail_id=detail,guardian_id=guardians[i],child_id=1001+i,facility_id=ref('facility'),year_month=ym,copayment_amount=share,other_charges=200,total_amount=total,payment_status=pay,payment_method='cash' if i%2 else 'bank_transfer',paid_amount=total if pay=='paid' else (100 if pay=='partial' else 0),paid_at=stamp(today-timedelta(days=2)) if pay=='paid' else None,due_date=fmt(today-timedelta(days=1) if pay=='overdue' else today+timedelta(days=14)),notes='【架空】仮数値。請求書の実送信は行いません。')
            if i==3:add(f'return{ym}','claim_returns',facility_id=ref('facility'),billing_detail_id=detail,year_month=ym,child_id=1004,return_code='DEMO',return_reason='【架空】受給者証の内容確認が必要なケース',original_amount=amount,status=['returned','resubmitting','resubmitted','resolved'][mi],received_at=fmt(today-timedelta(days=5)))
            if i==4:add(f'errorclaim{ym}','error_claims',facility_id=ref('facility'),billing_detail_id=detail,original_year_month=ym,child_id=1005,claim_type='partial_correction',reason='【架空】仮数値の訂正例',status=['draft','submitted','accepted','rejected'][mi])
        for ci,i in enumerate([2,5,8]):
            management=add(f'cap{ym}-{i}','copayment_cap_managements',child_id=1001+i,year_month=ym,managing_facility_id=ref('facility'),cap_amount=37200,total_copayment=6000,adjusted_copayment=4000,management_result='2',status=['draft','confirmed','submitted'][ci],form_type='paper' if ci==0 else 'electronic',contract_status='contracted',remarks='【架空・画面確認専用】上限管理の状態と表示を確認。')
            add(f'capdetail{ym}-{i}','copayment_cap_details',copayment_cap_management_id=management,facility_id=ref('facility'),facility_name='【テスト】事業所',total_amount=40000,copayment_amount=4000,adjusted_amount=3000,is_managing_facility=True,billable_facility_type='App\\Models\\Facility',billable_facility_id=ref('facility'))
            add(f'capexternal{ym}-{i}','copayment_cap_details',copayment_cap_management_id=management,facility_id=None,facility_name='【架空】連携先サンプル事業所',total_amount=20000,copayment_amount=2000,adjusted_amount=1000,is_managing_facility=False,billable_facility_type='App\\Models\\ExternalFacility',billable_facility_id=external)
        for j,category in enumerate(['rent','utilities','communications','supplies','vehicle','training','welfare','others']):add(f'expense{ym}-{category}','monthly_expenses',facility_id=ref('facility'),year_month=ym,category=category,amount=5000*(j+1),note='【架空】損益画面確認用の仮数値')
    for i,(subject,category,status) in enumerate([('利用日変更','schedule','open'),('支援について','support','in_progress'),('請求内容の確認','billing','closed'),('ご意見の受付','complaint','open'),('見学について','other','closed')]):
        add(f'inquiry{i}','inquiries',child_id=1001+i,guardian_id=guardians[i],staff_id=staff[1] if i else None,contact_method=['tel','line','email','in_person','other'][i],contacted_at=stamp(today-timedelta(days=i)),category=category,subject='【架空】'+subject,content='【架空】問い合わせ内容の確認用です。',response='【架空】担当者が回答しました。' if status=='closed' else None,status=status,is_escalated=i==3)
    for i,status in enumerate(['draft','scheduled','sent','failed']):
        notice=add(f'notification{i}','notifications',facility_id=ref('facility'),staff_id=ref('admin_staff'),title='【架空】'+['来月の予定','行事のお知らせ','持ち物のご案内','配信失敗の表示確認'][i],body='【架空】表示確認専用です。メール・LINEへの実配信は行いません。',type=['general','event','closure','individual'][i],channel='email',status=status,send_at=stamp(today+timedelta(days=14)) if status=='scheduled' else None,sent_count=1 if status=='sent' else 0,failed_count=1 if status=='failed' else 0)
        add(f'notify-target{i}','notification_targets',notification_id=notice,guardian_id=guardians[i],channel='email',address=f'child-{i+1:02d}@example.test',status='sent' if status=='sent' else ('failed' if status=='failed' else 'pending'),error_message='【架空】テスト用の送信エラー表示' if status=='failed' else None)
    fiscal=str(today.year if today.month>=4 else today.year-1)
    for i,kind in enumerate(['abuse_prevention','restraint']):
        for category in ['committee','training']:add(kind+category,'prevention_committees',facility_id=ref('facility'),type=kind,category=category,held_at=stamp(today-timedelta(days=30+i)),attendees=staff_names[:4],minutes='【架空】手順・記録方法を確認する訓練資料です。')
    for kind in ['infection','disaster']:add(kind,'business_continuity_plans',facility_id=ref('facility'),type=kind,established_at=fmt(month(today,-12)),last_reviewed_at=fmt(month(today,-1)),last_training_at=fmt(today-timedelta(days=20)) if kind=='disaster' else None)
    add('safety','safety_plans',facility_id=ref('facility'),fiscal_year=fiscal,established_at=fiscal+'-04-01',last_reviewed_at=fmt(today-timedelta(days=10)))
    add('evaluation','self_evaluations',facility_id=ref('facility'),fiscal_year=fiscal,guardian_survey_at=fmt(month(today,-1)),published_at=None)
    add('restraint-training','physical_restraint_records',child_id=1001,staff_id=staff[1],occurred_at=stamp(today-timedelta(days=30)),duration_minutes=1,method='【架空・訓練事例】危険回避の記録様式確認',reason='【架空・訓練事例】実際の支援事実ではありません。',guardian_notified_at=stamp(today-timedelta(days=30)))
    add('fixture-audit','audit_logs',user_id=ref('admin_user'),facility_id=ref('facility'),action='created',auditable_type='App\\Models\\Child',auditable_id=1001,old_values=None,new_values={'source':VERSION,'purpose':'架空データの画面確認'},ip_address='127.0.0.1',user_agent='manual-demo synthetic fixture')
    summaries={k:dict(Counter(row['table'] for row in v)) for k,v in ops.items()}
    return {'version':VERSION,'anchor':fmt(today),'months':[previous.strftime('%Y-%m'),first.strftime('%Y-%m'),following.strftime('%Y-%m')],
        'operations':ops,'counts':summaries,'delivery_notes':notes[-6:],'cancelled':cancelled,
        'walkthrough':{'today':fmt(today),'capacity_warning':fmt(first.replace(day=5)),'staff_shortage':fmt(first.replace(day=12)),
            'leave':fmt(first.replace(day=15)),'qualification_shortage':fmt(first.replace(day=20)),'holiday':fmt(first.replace(day=25)),'facility_closure':fmt(closure)},
        'limits':['請求金額・コードは画面確認用の仮数値であり、正式請求・計算ロジックの合格判定には使用しません。','実際の添付ファイル、メール・LINEの実送信、Stripe決済は生成しません。']}

if __name__=='__main__':
    result=build(date.fromisoformat(sys.argv[1]) if len(sys.argv)>1 else None)
    json.dump(result,sys.stdout,ensure_ascii=False,separators=(',',':'))
