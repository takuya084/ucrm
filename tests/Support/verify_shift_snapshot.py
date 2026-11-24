from pathlib import Path
import hashlib,importlib.util,json,os,shutil,sqlite3,subprocess,sys,tempfile
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
    # Exercise the exact recovery PHP helper against the full Eloquent-created DB,
    # with committed data in an uncheckpointed WAL and a read-only source directory.
    source=Path(tmp)/'source';source.mkdir(mode=0o700)
    shutil.copyfile(after,source/'database.sqlite');shutil.copyfile(after,source/'reference.sqlite')
    writer=sqlite3.connect(source/'database.sqlite')
    try:
        writer.execute('PRAGMA journal_mode=WAL')
        fid,name=writer.execute('SELECT id,name FROM facilities ORDER BY id LIMIT 1').fetchone()
        writer.execute('UPDATE facilities SET name=? WHERE id=?',(name+' temporary',fid));writer.commit()
        writer.execute('UPDATE facilities SET name=? WHERE id=?',(name,fid));writer.commit()
        names=['database.sqlite','database.sqlite-wal','reference.sqlite']
        expected={n:hashlib.sha256((source/n).read_bytes()).hexdigest() for n in names}
        config={'source':str(source),'workspace':str(Path(tmp)/'private-check'),'reference':'reference.sqlite','expected':expected}
        os.chmod(source,0o500)
        php='require $argv[1];echo json_encode(shift_verify_sqlite_copy(json_decode($argv[2],true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);'
        result=subprocess.run(['php','-r',php,str(root/'tools/manual-demo/sqlite_copy_check.php'),json.dumps(config)],capture_output=True,text=True,timeout=30,check=True)
        assert json.loads(result.stdout)['ok'] is True
        assert expected=={n:hashlib.sha256((source/n).read_bytes()).hexdigest() for n in names}
    finally:
        os.chmod(source,0o700);writer.close()
print('PASS: real Eloquent sample data, automatic Child audit logs and all original rows verified')
