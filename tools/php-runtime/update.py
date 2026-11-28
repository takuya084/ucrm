#!/usr/bin/env python3
"""Replace only the guarded demo's PHP runtime, retaining application files and data."""
import copy
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import time
import urllib.request

HERE=Path(__file__).resolve().parent
STATE=Path.home()/'projects/openclaw-data/workspace/p-yoyaku/manual-demo/.runtime'
DOCKER=['docker','--context','desktop-linux']
PROJECT='p-u-manual-demo'
STAGE='php-runtime-security-20261129-v1'
TARGET='8.3.37'
APP_VERSIONS={'p':'e94b0d63ba630a4afca6ffea89a7140ef72c0b9d','u':'e4cc815bff87386b202adfa1759848cc2dc612b9'}
SERVICES=['p','u','gateway','p-worker','u-worker']

def require(ok,message):
    if not ok:raise RuntimeError(message)

def read(path):
    require(path.is_file() and not path.is_symlink(),'通常ファイルを確認できません: '+str(path))
    return json.loads(path.read_text())

def write(path,value):
    with path.open('x') as stream:
        os.chmod(path,0o600);json.dump(value,stream,ensure_ascii=False,indent=2);stream.write('\n')

def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()

def run(*args,input=None,log=None,timeout=180,progress=None):
    if progress:
        require(log is not None,'構築ログの保存先がありません。')
        started=time.monotonic()
        with log.open('ab') as output:
            os.chmod(log,0o600);process=subprocess.Popen(args,stdout=output,stderr=subprocess.STDOUT)
            while True:
                try:code=process.wait(timeout=20);break
                except subprocess.TimeoutExpired:
                    elapsed=int(time.monotonic()-started)
                    if elapsed>=timeout:
                        process.kill();process.wait();raise RuntimeError('構築時間の上限に達しました: '+log.name)
                    print(progress+'（'+str(elapsed)+'秒経過）',flush=True)
        require(code==0,'構築で停止しました: '+log.name+'（終了コード '+str(code)+'）')
        return ''
    result=subprocess.run(args,input=input,text=True,capture_output=True,timeout=timeout)
    if log:
        with log.open('a') as output:os.chmod(log,0o600);output.write(result.stdout);output.write(result.stderr)
    if result.returncode:
        detail=next((line[:350] for line in result.stderr.splitlines() if line.startswith('FAIL:')),'終了コード '+str(result.returncode))
        raise RuntimeError('処理で停止しました（'+(log.name if log else 'Docker')+'）: '+detail)
    return result.stdout

def compose(path,*args,**kwargs):return run(*DOCKER,'compose','-p',PROJECT,'-f',str(path),*args,**kwargs)

def payload():
    manifest=read(HERE/'files.sha256.json')
    for path in HERE.rglob('*'):
        rel=path.relative_to(HERE)
        if '__pycache__' in rel.parts or path.name=='.DS_Store' or path.name.startswith('._'):continue
        require(not path.is_symlink(),'更新ファイル内のリンクは使用できません。')
        if path.is_file():require(str(rel) in manifest or str(rel)=='files.sha256.json','想定外の更新ファイルです。')
    for name,value in manifest.items():
        path=HERE/name
        require(not Path(name).is_absolute() and '..' not in Path(name).parts and path.is_file() and not path.is_symlink() and digest(path)==value,'更新ファイルが一致しません: '+name)

def image_meta(image):
    source=(HERE/'runtime/checks/image-meta.php').read_text()
    return json.loads(run(*DOCKER,'run','--rm','-i','--pull','never','--network','none','--read-only','--cap-drop','ALL','--security-opt','no-new-privileges:true','--memory','256m','--pids-limit','128','--entrypoint','php',image,input=source))

def compatible(before,after):
    require(after['php']==TARGET,'修正済みPHP '+TARGET+' ではありません。')
    require(before['hashes']==after['hashes'],'アプリ本体・デモ設定の内容が変わっています。')
    require(set(before['extensions'])<=set(after['extensions']),'既存のPHP拡張が不足しています。')
    require(before['ini']==after['ini'],'PHPの基本設定が一致しません。')
    for key,value in before['gd'].items():
        if value is True:require(after['gd'].get(key) is True,'既存の画像形式に対応していません: '+key)

def preflight(profile):
    require(STATE.resolve()==STATE.absolute(),'標準のデモ保存先ではありません。')
    require(profile.get('name')==PROJECT and read(STATE/'versions.json')==APP_VERSIONS,'v4のUPDATED後に実行してください。')
    receipt=read(STATE/'upgrades/print-pdf-billing-20261128-v4/completed.json')
    require(digest(STATE/'compose.json')==receipt['compose_sha256'],'v4の起動設定と一致しません。')
    for name in ['privacy-update-in-progress','review-samples-in-progress','print-pdf-update-in-progress','php-runtime-update-in-progress']:
        require(not (STATE/name).exists() and not (STATE/name).is_symlink(),'別の更新の途中記録があります: '+name)
    items=json.loads(run(*DOCKER,'inspect',*[PROJECT+'-'+name+'-1' for name in SERVICES]));require(len(items)==5,'対象コンテナが揃っていません。')
    running=[];platforms=[];metas={}
    for item,name in zip(items,SERVICES):
        labels=item['Config'].get('Labels') or {};status=item['State']
        require(labels.get('com.docker.compose.project')==PROJECT and labels.get('com.docker.compose.service')==name and labels.get('com.docker.compose.project.config_files')==str(STATE/'compose.json'),'対象コンテナの所属が一致しません。')
        require(item['Config']['Image']==profile['services'][name]['image'],'起動中の版が一致しません。')
        clean=name.endswith('-worker') and status['Status']=='exited' and status.get('ExitCode')==0 and not status.get('OOMKilled') and not status.get('Error')
        require(status['Status']=='running' or clean,'コンテナの状態を確認してください: '+name)
        require(item['HostConfig']['ReadonlyRootfs'] and not any(m['Type']=='bind' for m in item['Mounts']),'対象デモの保存設定が一致しません。')
        if name!='gateway':
            mounts=[m for m in item['Mounts'] if m['Destination']=='/data']
            require(len(mounts)==1 and mounts[0]['Type']=='volume' and mounts[0]['Name']==PROJECT+'_private_'+name[0]+'-data-20261127-v1','対象DBが一致しません。')
        if status['Status']=='running':running.append(name)
    for kind in ['p','u']:
        service=profile['services'][kind];info=json.loads(run(*DOCKER,'image','inspect',service['image']))[0]
        require(info['Id']==items[SERVICES.index(kind)]['Image'] and info['Os']=='linux','既存イメージの内容が一致しません。')
        platforms.append('linux/'+info['Architecture'])
        for name in [kind,kind+'-worker','init-'+kind]:require(profile['services'][name]['image']==service['image'] and profile['services'][name]['environment']['APP_KEY']==service['environment']['APP_KEY'],'サービスの版・鍵が一致しません。')
        metas[kind]=image_meta(service['image']);require(metas[kind]['php']=='8.3.35','対応する更新前のPHPではありません: '+kind)
    require(len(set(platforms))==1 and platforms[0] in ['linux/amd64','linux/arm64'],'両アプリのCPU構成を確認できません。')
    return running,metas,platforms[0]

def candidate(profile,images):
    fresh=copy.deepcopy(profile)
    for kind in ['p','u']:
        for name in [kind,kind+'-worker','init-'+kind]:fresh['services'][name]['image']=images[kind]
    return fresh

def php(path,kind,phase,area,live=False):
    args=['exec','-T',kind,'php'] if live else ['run','--rm','-T','--no-deps','--pull','never','init-'+kind,'php']
    source="<?php\n$runtimePhase='"+phase+"';\n"+(HERE/'runtime/checks/data-check.php').read_text().removeprefix('<?php')
    return json.loads(compose(path,*args,input=source,log=area/(phase+'-'+kind+'.log')))

def web_up(path,area):compose(path,'up','-d','--no-deps','--no-build','--pull','never','p','u',log=area/'docker.log')

def gateway_up(path,area):compose(path,'up','-d','--no-deps','--no-build','--pull','never','--force-recreate','gateway',log=area/'docker.log')

def health():
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    for port in [18081,18082]:
        for attempt in range(20):
            try:
                with opener.open('http://127.0.0.1:'+str(port)+'/demo-health',timeout=2) as response:
                    if json.load(response)=={'demo':True}:break
            except (OSError,ValueError):pass
            time.sleep(1)
        else:raise RuntimeError('アプリの起動を確認できません。')

def swap(area,prefix,profile):
    temporary=area/(prefix+'-compose.json');write(temporary,profile);temporary.replace(STATE/'compose.json')

def main():
    payload();area=STATE/'upgrades'/STAGE;path=STATE/'compose.json';lock=STATE/'php-runtime-update-in-progress'
    if (area/'completed.json').is_file():
        saved=read(area/'completed.json');require(digest(path)==saved['compose_sha256'] and read(STATE/'versions.json')==APP_VERSIONS and not lock.exists(),'完了記録と現在の設定が一致しません。')
        for kind in ['p','u']:
            require(php(path,kind,'verify',area,True)['php']==TARGET,'PHPの版を確認できません。')
        health();print('CURRENT: 両アプリのPHP '+TARGET+' は適用済みです。');return
    print('1/6 現在のPHP・アプリ本体・設定を確認しています。',flush=True)
    require(not area.exists() and not area.is_symlink(),'この更新の途中記録があります。再実行せず結果を共有してください。')
    profile=read(path);running,before,platform=preflight(profile)
    area.mkdir(mode=0o700)
    with lock.open('x') as stream:os.chmod(lock,0o600);stream.write(STAGE)
    stopped=False;snapshots=[];fresh=None
    try:
        write(area/'before-compose.json',profile);write(area/'before-versions.json',APP_VERSIONS)
        build=area/'runtime-build';shutil.copytree(HERE/'runtime',build)
        required=sorted(set(before['p']['extensions']+before['u']['extensions']));write(build/'extensions.json',required)
        print('2/6 公式PHP '+TARGET+' と既存の拡張機能を準備します。デモはまだ稼働しています。',flush=True)
        run(*DOCKER,'pull','--platform',platform,'php:'+TARGET+'-cli',log=area/'pull.log',timeout=900,progress='公式PHPを取得中')
        runtime='manual-demo-runtime:php8.3.37-20261129-v1'
        run(*DOCKER,'build','--pull=false','--platform',platform,'--build-arg','PHP_IMAGE=php:'+TARGET+'-cli','-t',runtime,'-f',str(build/'Dockerfile.runtime'),str(build),log=area/'runtime-build.log',timeout=900,progress='PHPと拡張機能を構築中')
        print('3/6 アプリの内容を変えずに、新しいPHPへ載せ替えています。',flush=True)
        images={kind:'manual-demo-'+kind+':php8.3.37-20261129-v1' for kind in ['p','u']}
        for kind in ['p','u']:
            run(*DOCKER,'build','--pull=false','--network','none','--platform',platform,'--build-arg','APP_IMAGE='+profile['services'][kind]['image'],'--build-arg','RUNTIME_IMAGE='+runtime,'-t',images[kind],'-f',str(build/'Dockerfile.app'),str(build),log=area/('build-'+kind+'.log'),timeout=300,progress=kind+'のPHPを置換中')
            compatible(before[kind],image_meta(images[kind]))
        fresh=candidate(profile,images);prepared=area/'prepared-compose.json';write(prepared,fresh);compose(prepared,'config','-q')
        require(read(path)==profile and read(STATE/'versions.json')==APP_VERSIONS,'準備中に起動設定が変わりました。')
        print('4/6 デモを一時停止し、暗号化済みDBを保持します。完了まで画面操作をお待ちください。',flush=True)
        stopped=True;compose(path,'stop','--timeout','45',*SERVICES,log=area/'docker.log')
        for kind in ['p','u']:php(path,kind,'snapshot',area);snapshots.append(kind)
        swap(area,'activated',fresh);web_up(path,area)
        print('5/6 PHPの版・既存データの復号・PDF・両アプリの連携を確認しています。',flush=True)
        checks={kind:php(path,kind,'check',area,True) for kind in ['p','u']}
        pdf=php(path,'u','pdf',area,True);integration=php(path,'u','integration',area,True)
        require(pdf.get('pdf') is True and integration.get('integration') is True,'PDF・連携を確認できません。')
        print('6/6 デモを再開しています。',flush=True)
        gateway_up(path,area);health();compose(path,'up','-d','--no-deps','--no-build','--pull','never','p-worker','u-worker',log=area/'docker.log')
        write(area/'completed.json',{'version':STAGE,'php':TARGET,'compose_sha256':digest(path),'checks':checks,'pdf':pdf,'integration':integration})
        lock.unlink();print('UPDATED: p-yoyaku・UCRMともPHP '+TARGET+' へ更新しました。')
        print('データ・設定・暗号鍵・アプリ本体の内容を保持しました。ブラウザーを再読み込みしてください。')
    except BaseException as error:
        try:
            if stopped:
                compose(path,'stop','--timeout','45',*SERVICES,log=area/'docker.log')
                old=area/'before-compose.json'
                for kind in reversed(snapshots):php(old,kind,'restore',area)
                swap(area,'restored',profile);web_up(path,area);gateway_up(path,area);health()
                workers=[name for name in running if name.endswith('-worker')]
                if workers:compose(path,'up','-d','--no-deps','--no-build','--pull','never',*workers,log=area/'docker.log')
            write(area/'stopped.json',{'error':type(error).__name__,'returned_to_previous_database':True})
        except BaseException as recovery:print('元のデモへの復帰も停止しました: '+str(recovery),file=sys.stderr)
        print('STOPPED: 自動再試行はしません。ログ末尾を共有してください: '+str(area),file=sys.stderr);raise

if __name__=='__main__':
    try:main()
    except Exception as error:print('ERROR: '+str(error),file=sys.stderr);sys.exit(1)
