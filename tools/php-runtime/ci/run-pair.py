#!/usr/bin/env python3
"""Private-repository CI: upgrade the real P/U source snapshots without publishing them."""
import argparse
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import time

parser=argparse.ArgumentParser();parser.add_argument('--p-source',type=Path,required=True);parser.add_argument('--u-source',type=Path,required=True);args=parser.parse_args()
tool=args.u_source/'tools/php-runtime'
spec=importlib.util.spec_from_file_location('runtime_update',tool/'update.py');update=importlib.util.module_from_spec(spec);spec.loader.exec_module(update)
prefix='ci-php-runtime-'+secrets.token_hex(4);network=prefix+'-net';volumes={k:prefix+'-'+k for k in ['p-data','u-data','link-data']};containers=[];images=[];keys={k:'base64:'+base64.b64encode(secrets.token_bytes(32)).decode() for k in ['p','u']}
root=Path(tempfile.mkdtemp(prefix=prefix+'-'));os.chmod(root,0o700)

def run(*command,input=None):
    p=subprocess.run(command,input=input,text=True,capture_output=True,timeout=900)
    if p.returncode:
        print(p.stdout[-10000:]);print(p.stderr[-5000:]);raise RuntimeError('CI command failed: '+command[0])
    return p.stdout

def php_source(image,source):
    return json.loads(run('docker','run','--rm','-i','--network','none','--read-only','--entrypoint','php',image,input=source))

def options(kind):
    return ['--network',network,'--read-only','--cap-drop','ALL','--security-opt','no-new-privileges:true','--memory','768m','--pids-limit','128',
        '--tmpfs','/tmp:rw,nosuid,size=128m','--tmpfs','/app/storage:rw,nosuid,size='+('64m' if kind=='p' else '256m'),'--tmpfs','/app/bootstrap/cache:rw,nosuid,size=8m',
        '--mount','type=volume,src='+volumes[kind+'-data']+',dst=/data','--mount','type=volume,src='+volumes['link-data']+',dst=/link'+(',readonly' if kind=='u' else ''),
        '--env','MANUAL_DEMO=1','--env','DEMO_APP='+kind,'--env','APP_KEY='+keys[kind]]

def cli(kind,image,*command,input=None):return run('docker','run','--rm','-i',*options(kind),image,*command,input=input)

def phase(kind,image,operation,live=False):
    source="<?php\n$runtimePhase='"+operation+"';\n"+(tool/'checks/data-check.php').read_text().removeprefix('<?php')
    output=run('docker','exec','-i',prefix+'-'+kind,'php',input=source) if live else cli(kind,image,'php',input=source)
    report=json.loads(output);print(json.dumps({'kind':kind,'phase':operation,**report}));return report

def start(images_by_kind):
    for kind in ['p','u']:
        name=prefix+'-'+kind
        run('docker','run','-d','--name',name,'--network-alias',kind,*options(kind),images_by_kind[kind],'php','-S','0.0.0.0:8000','-t','/app/public','/demo/router.php');containers.append(name)
    for kind in ['p','u']:
        for attempt in range(20):
            p=subprocess.run(['docker','exec',prefix+'-'+kind,'php','-r',"$v=json_decode(file_get_contents('http://127.0.0.1:8000/demo-health'),true);exit(($v['demo']??false)===true?0:1);"],capture_output=True,text=True)
            if p.returncode==0:break
            time.sleep(1)
        else:raise RuntimeError('CI server did not start: '+kind)

def stop():
    while containers:
        name=containers.pop();run('docker','stop','--time','15',name);run('docker','rm',name)

try:
    runtime_context=root/'runtime';shutil.copytree(tool,runtime_context)
    (runtime_context/'extensions.json').write_text(json.dumps(['bcmath','gd','zip','mbstring','curl','pdo_sqlite','pcntl','intl']))
    runtimes={v:prefix+'-php:'+v for v in ['8.3.35','8.3.37']}
    for version,image in runtimes.items():
        print('BUILD_RUNTIME '+version,flush=True)
        run('docker','build','--pull','--build-arg','PHP_IMAGE=php:'+version+'-cli','-t',image,'-f',str(runtime_context/'Dockerfile.runtime'),str(runtime_context));images.append(image)
    dataset=run('python3',str(args.u_source/'tests/Fixtures/build-review-data.py'),'2026-11-28')
    before={};baseline={};after={}
    for kind,source in [('p',args.p_source),('u',args.u_source)]:
        context=root/kind;context.mkdir();app=context/'app'
        shutil.copytree(source,app,symlinks=True,ignore=shutil.ignore_patterns('.git','.env','.env.*','node_modules','storage','__pycache__'))
        cache=app/'bootstrap/cache'
        if cache.exists():shutil.rmtree(cache)
        fixture=app/'tests/Fixtures';fixture.mkdir(parents=True,exist_ok=True);shutil.copy2(args.u_source/'tests/Fixtures/review-data-engine.php',fixture/'review-data-engine.php')
        demo=context/'demo';demo.mkdir()
        for name in ['bootstrap.php','SqliteConnection.php','entrypoint.sh']:
            shutil.copy2(args.u_source/'tests/Fixtures/manual-demo'/name,demo/name)
        for name in ['router.php','console.php','seed.php']:shutil.copy2(tool/'ci'/name,demo/name)
        shutil.copy2(tool/'ci/populate.php',demo/'populate.php');shutil.copy2(args.u_source/'tools/demo-review/print-pdf-maintenance.php',demo/'print-pdf-maintenance.php')
        (demo/'dataset.json').write_text(dataset)
        (app/'public').mkdir(exist_ok=True);link=app/'public/storage'
        if link.is_symlink() or link.is_file():link.unlink()
        if not link.exists():link.symlink_to('/app/storage/app/public',target_is_directory=True)
        baseline[kind]=prefix+'-'+kind+':old';after[kind]=prefix+'-'+kind+':patched'
        run('docker','build','--network','none','--build-arg','RUNTIME_IMAGE='+runtimes['8.3.35'],'-t',baseline[kind],'-f',str(tool/'ci/Dockerfile.baseline'),str(context));images.append(baseline[kind])
        before[kind]=php_source(baseline[kind],(tool/'checks/image-meta.php').read_text());assert before[kind]['php']=='8.3.35'
        run('docker','build','--network','none','--build-arg','APP_IMAGE='+baseline[kind],'--build-arg','RUNTIME_IMAGE='+runtimes['8.3.37'],'-t',after[kind],'-f',str(tool/'Dockerfile.app'),str(tool));images.append(after[kind])
        update.compatible(before[kind],php_source(after[kind],(tool/'checks/image-meta.php').read_text()))
        print('APPLICATION_PRESERVED '+kind,flush=True)
    run('docker','network','create','--internal',network)
    for name in volumes.values():run('docker','volume','create',name)
    for kind in ['p','u']:print(cli(kind,baseline[kind],'php','/demo/seed.php').strip());print(cli(kind,baseline[kind],'php','/demo/populate.php').strip())
    for operation in ['prepare','commit']:json.loads(cli('u',baseline['u'],'php','/demo/print-pdf-maintenance.php',operation))
    for kind in ['p','u']:phase(kind,baseline[kind],'snapshot')
    start(after)
    for kind in ['p','u']:phase(kind,after[kind],'check',True)
    assert phase('u',after['u'],'integration',True)['integration']
    assert phase('u',after['u'],'pdf',True)['pdf']
    stop()
    for kind in ['p','u']:phase(kind,baseline[kind],'restore');phase(kind,baseline[kind],'restored')
    print('PASS: PHP 8.3.35 -> 8.3.37; both apps, encryption, unchanged data, integration, PDFs and rollback verified.')
finally:
    for name in containers:subprocess.run(['docker','rm','-f',name],capture_output=True)
    for name in volumes.values():subprocess.run(['docker','volume','rm',name],capture_output=True)
    subprocess.run(['docker','network','rm',network],capture_output=True)
    shutil.rmtree(root,ignore_errors=True)
