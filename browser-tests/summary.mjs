import fs from 'node:fs';
export default class Summary {
  onTestEnd(test,result) {
    const key=result.status==='passed'?'passed':result.status==='skipped'?'skipped':'failed';
    this[key]=(this[key]||0)+1;
  }
  onEnd(result) {
    const passed=result.status==='passed' && this.passed===38 && !this.failed && !this.skipped;
    fs.writeFileSync('summary.json',JSON.stringify({state:passed?'passed':'failed',passed:this.passed||0,
      failed:this.failed||0,skipped:this.skipped||0,automaticRetry:false})+'\n');
    if (!passed) return {status:'failed'};
  }
}
