from pathlib import Path
import json
import sqlite3

def verify_added_data(before_path, after_path, receipt):
    if receipt.get('email') != 'shift-demo-admin@example.invalid' or receipt.get('staff_count') != 10 or len(receipt.get('password', '')) < 20:
        raise RuntimeError('サンプルの登録結果が想定と異なります。')
    fid = receipt.get('facility_id')
    if not isinstance(fid, int) or fid <= 0 or len(receipt.get('shifts', [])) != 2:
        raise RuntimeError('サンプル事業所・月の記録が不正です。')
    with sqlite3.connect(before_path.resolve().as_uri() + '?mode=ro&immutable=1', uri=True) as before, sqlite3.connect(after_path.resolve().as_uri() + '?mode=ro&immutable=1', uri=True) as after:
        for db in [before, after]:
            if db.execute('PRAGMA integrity_check').fetchone()[0] != 'ok' or db.execute('PRAGMA foreign_key_check').fetchone() is not None:
                raise RuntimeError('データの整合性を確認できません。')
        sql = "SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name"
        if before.execute(sql).fetchall() != after.execute(sql).fetchall():
            raise RuntimeError('既存DBの定義が変わっています。')
        added = {}
        names = [r[0] for r in before.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")]
        for name in names:
            quoted = '"' + name.replace('"', '""') + '"'
            old = before.execute('SELECT rowid,* FROM ' + quoted + ' ORDER BY rowid').fetchall()
            cutoff = old[-1][0] if old else 0
            retained = after.execute('SELECT rowid,* FROM ' + quoted + ' WHERE rowid<=? ORDER BY rowid', (cutoff,)).fetchall()
            if old != retained:
                raise RuntimeError('既存の入力内容が変わっています: ' + name)
            after.row_factory = sqlite3.Row
            added[name] = [dict(row) for row in after.execute('SELECT * FROM ' + quoted + ' WHERE rowid>? ORDER BY rowid', (cutoff,))]
            after.row_factory = None
        allowed = {'facilities','users','staff','staff_qualifications','staff_work_patterns','shift_labels','monthly_shifts','shift_entries','shift_day_notes','schools','children','child_schedules','audit_logs'}
        for name, rows in added.items():
            if name not in allowed and rows:
                raise RuntimeError('想定外のデータが追加されています: ' + name)
        expected = {'facilities':1,'users':10,'staff':10,'staff_work_patterns':70,'shift_labels':7,'monthly_shifts':2,'schools':1,'children':12}
        for name, count in expected.items():
            if len(added.get(name, [])) != count:
                raise RuntimeError('追加件数が一致しません: ' + name)
        if added['facilities'][0]['id'] != fid or added['facilities'][0]['name'] != '【デモ】シフト確認用（職員10名）':
            raise RuntimeError('確認用事業所が一致しません。')
        staff_ids = {r['id'] for r in added['staff']}
        user_ids = {r['user_id'] for r in added['staff']}
        child_ids = {r['id'] for r in added['children']}
        shift_ids = {r['id'] for r in added['monthly_shifts']}
        if {r['id'] for r in added['users']} != user_ids or {r['id'] for r in receipt['shifts']} != shift_ids:
            raise RuntimeError('追加ユーザー・月の所属が一致しません。')
        for name in ['staff','staff_work_patterns','shift_labels','monthly_shifts','schools','children']:
            if any(r['facility_id'] != fid for r in added[name]): raise RuntimeError('既存事業所への追加を検出しました。')
        for name in ['staff_qualifications','staff_work_patterns','shift_entries']:
            if any(r['staff_id'] not in staff_ids for r in added[name]): raise RuntimeError('既存職員への追加を検出しました。')
        if any(r['child_id'] not in child_ids for r in added['child_schedules']): raise RuntimeError('既存児童への追加を検出しました。')
        for name in ['shift_entries','shift_day_notes']:
            if any(r['monthly_shift_id'] not in shift_ids for r in added[name]): raise RuntimeError('既存シフトへの追加を検出しました。')
        expected_entries = 0
        import calendar
        for month in receipt['shifts']:
            year, number = map(int, month['month'].split('-'))
            expected_entries += calendar.monthrange(year, number)[1] * 10
        if len(added['shift_entries']) != expected_entries: raise RuntimeError('サンプルシフトの行数が不足しています。')

        # CLI creation has no signed-in actor. Permit only the exact Child-created audits.
        audits = added.get('audit_logs', [])
        if len(audits) != len(child_ids) or {r.get('auditable_id') for r in audits} != child_ids:
            raise RuntimeError('追加した児童の監査ログ件数・対象が一致しません。')
        children = {r['id']: r for r in added['children']}
        for audit in audits:
            if audit.get('action') != 'created' or audit.get('auditable_type') != 'App\\Models\\Child' or audit.get('old_values') is not None or audit.get('user_id') is not None or audit.get('facility_id') is not None:
                raise RuntimeError('確認用児童のCLI作成に対応しない監査ログを検出しました。')
            try:
                values = json.loads(audit['new_values'])
            except (KeyError, TypeError, ValueError):
                raise RuntimeError('監査ログの作成内容を確認できません。')
            child = children[audit['auditable_id']]
            if not isinstance(values, dict) or values.get('id') != child['id'] or values.get('facility_id') != fid:
                raise RuntimeError('監査ログと確認用児童の所属が一致しません。')
            if any(key not in child or child[key] != value for key, value in values.items()):
                raise RuntimeError('監査ログと追加した児童の内容が一致しません。')

