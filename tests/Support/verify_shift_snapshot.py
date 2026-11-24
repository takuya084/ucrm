from pathlib import Path
import importlib.util,json,sqlite3,sys,tempfile
root=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('proof',root/'tools/manual-demo/shift_sample_proof.py')
proof=importlib.util.module_from_spec(spec);spec.loader.exec_module(proof)
data=json.loads(Path(sys.argv[1]).read_text())
def materialize(path,snapshot):
    with sqlite3.connect(path) as db:
        for s in snapshot['schema']:
            if s['type']=='table':db.execute(s['sql'])
        for name,table in snapshot['tables'].items():
            q='"'+name.replace('"','""')+'"'
            columns=['rowid']+table['columns']
            sql='INSERT INTO '+q+'('+','.join('"'+c.replace('"','""')+'"' for c in columns)+') VALUES('+','.join('?' for c in columns)+')'
            db.executemany(sql,table['rows'])
        for s in snapshot['schema']:
            if s['type']!='table' and s['sql']:db.execute(s['sql'])
with tempfile.TemporaryDirectory(prefix='shift-audit-proof-') as tmp:
    before=Path(tmp)/'before.sqlite';after=Path(tmp)/'after.sqlite'
    materialize(before,data['before']);materialize(after,data['after'])
    proof.verify_added_data(before,after,data['receipt'])
print('PASS: real Eloquent sample data, automatic Child audit logs and all original rows verified')
