<?php
namespace Tests\Feature;

use App\Models\{Facility,User,Staff,Child,AuditLog};
use App\Support\ShiftDemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth,DB};
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ShiftDemoAuditProofTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): array
    {
        $pdo=DB::connection()->getPdo();
        $schema=$pdo->query("SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll(\PDO::FETCH_ASSOC);
        $tables=[];
        foreach ($schema as $item) {
            if ($item['type']!=='table') continue;
            $name=$item['name']; $quoted='"'.str_replace('"','""',$name).'"';
            $tables[$name]=[
                'columns'=>array_column($pdo->query('PRAGMA table_info('.$quoted.')')->fetchAll(\PDO::FETCH_ASSOC),'name'),
                'rows'=>$pdo->query('SELECT rowid,* FROM '.$quoted.' ORDER BY rowid')->fetchAll(\PDO::FETCH_NUM),
            ];
        }
        return compact('schema','tables');
    }

    public function test_cli_sample_proof_accepts_real_created_audits_and_preserves_existing_data(): void
    {
        Auth::logout();
        $facility=Facility::create(['name'=>'Existing protected facility','billing_type'=>'free','is_active'=>true]);
        $user=User::factory()->create();
        Staff::create(['user_id'=>$user->id,'facility_id'=>$facility->id,'name'=>'Existing staff','role'=>'admin','is_active'=>true]);
        Child::create(['facility_id'=>$facility->id,'name'=>'Existing protected child','contract_status'=>'active']);
        $before=$this->snapshot();$auditCount=AuditLog::count();
        $password='Synthetic-local-password-123!';
        $receipt=app(ShiftDemoData::class)->create('2026-12',$password)+['password'=>$password];
        $this->assertSame(12,AuditLog::count()-$auditCount);
        $after=$this->snapshot();
        $file=tempnam(sys_get_temp_dir(),'shift-audit-regression-');
        try {
            file_put_contents($file,json_encode(compact('before','after','receipt'),JSON_THROW_ON_ERROR));
            $process=new Process(['python3',base_path('tests/Support/verify_shift_snapshot.py'),$file]);
            $process->setTimeout(30);$process->run();
            $this->assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());
            $this->assertStringContainsString('real Eloquent sample data',$process->getOutput());
        } finally {if (is_file($file)) unlink($file);}
    }
}
