<?php

namespace Tests\Feature\Billing;

use App\Models\{BillingPeriod, Child, CopaymentCapManagement, Facility, Guardian, GuardianInvoice, ServiceCodeMaster, Staff, UsageRecord, User};
use App\Services\Billing\{BeneficiaryPaymentListPdfService, BillingCalculationService, CopaymentCapPdfService, GuardianInvoicePdfService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class PdfRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private function facility(): Facility
    {
        return Facility::create(['name'=>'架空PDF事業所','capacity_per_day'=>10,'area_unit_price'=>10,'billing_type'=>'free','is_active'=>true]);
    }

    public function test_both_cap_outputs_create_font_metrics_when_default_font_directory_is_missing(): void
    {
        Storage::fake('local');
        config(['dompdf.options.font_dir'=>storage_path('unprepared-pdf-fonts'), 'dompdf.options.font_cache'=>storage_path('unprepared-pdf-fonts')]);
        $this->assertDirectoryDoesNotExist(storage_path('unprepared-pdf-fonts'));
        $facility=$this->facility();
        $admin=User::factory()->create();
        Staff::create(['facility_id'=>$facility->id,'user_id'=>$admin->id,'name'=>$admin->name,'role'=>'admin','is_active'=>true]);
        $this->actingAs($admin);
        foreach(range(1,2) as $index){
            $child=Child::create(['facility_id'=>$facility->id,'name'=>'架空同姓同名','contract_status'=>'active']);
            CopaymentCapManagement::create(['child_id'=>$child->id,'year_month'=>'2026-11','managing_facility_id'=>$facility->id,'cap_amount'=>4600,'management_result'=>'2','status'=>'draft']);
        }
        $zipPath=app(CopaymentCapPdfService::class)->generateBundle($facility->id,'2026-11');
        $archive=new ZipArchive();$this->assertTrue($archive->open(Storage::disk('local')->path($zipPath)));
        $this->assertSame(2,$archive->numFiles);
        $this->assertNotSame($archive->getNameIndex(0),$archive->getNameIndex(1));
        foreach(range(0,1) as $i)$this->assertStringStartsWith('%PDF',$archive->getFromIndex($i));
        $archive->close();
        $listPath=app(BeneficiaryPaymentListPdfService::class)->generate($facility->id,'2026-11');
        $this->assertStringStartsWith('%PDF',Storage::disk('local')->get($listPath));
        $this->assertDirectoryExists(storage_path('framework/cache/dompdf'));
        $this->get('/billing/cap-management/payment-list?year_month=2026-11')->assertOk();
        $this->get('/billing/cap-management/pdf-bundle?year_month=2026-11')->assertOk();
    }

    public function test_empty_cap_bundle_gives_a_validation_message_instead_of_a_missing_zip(): void
    {
        Storage::fake('local');$facility=$this->facility();
        $this->expectException(ValidationException::class);
        app(CopaymentCapPdfService::class)->generateBundle($facility->id,'2026-11');
    }

    private function calculatedPeriod(): BillingPeriod
    {
        $facility=$this->facility();$child=Child::create(['facility_id'=>$facility->id,'name'=>'架空請求児童','contract_status'=>'active']);
        $guardian=Guardian::create(['name'=>'架空保護者']);$child->guardians()->attach($guardian->id,['is_primary'=>true]);
        $child->recipientCertificates()->create(['service_type'=>'houday','status'=>'active','valid_from'=>'2026-10-01','valid_to'=>'2027-03-31','copayment_rate'=>10,'copayment_cap_monthly'=>4600]);
        $child->supportPlans()->create(['plan_date'=>'2026-10-01','valid_from'=>'2026-10-01','planned_duration_minutes'=>120]);
        ServiceCodeMaster::create(['revision_date'=>'2026-01-01','service_type'=>'houday','service_code'=>'999901','service_name'=>'架空テスト専用','unit_count'=>100,'category'=>'base','valid_from'=>'2026-01-01']);
        UsageRecord::create(['facility_id'=>$facility->id,'child_id'=>$child->id,'date'=>'2026-11-02','status'=>'attended','is_school_day'=>true,'billing_target'=>true]);
        DB::table('child_school_days')->insert(['facility_id'=>$facility->id,'child_id'=>$child->id,'date'=>'2026-11-02','is_school_day'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return app(BillingCalculationService::class)->calculateMonthlyBilling($facility->id,'2026-11');
    }

    public function test_invoice_regeneration_keeps_recorded_payments_expenses_and_notes(): void
    {
        $period=$this->calculatedPeriod();$service=app(GuardianInvoicePdfService::class);
        $invoice=$service->createInvoicesFromBillingPeriod($period)[0];
        $invoice->update(['other_charges'=>200,'total_amount'=>$invoice->copayment_amount+200,'payment_status'=>'paid','paid_amount'=>$invoice->copayment_amount+200,'paid_at'=>'2026-11-10','payment_method'=>'cash','notes'=>'架空の入金確認済みメモ']);
        $before=$invoice->fresh()->only(['other_charges','total_amount','payment_status','paid_amount','paid_at','payment_method','notes']);
        $again=$service->createInvoicesFromBillingPeriod($period)[0];
        $this->assertSame($invoice->id,$again->id);
        $this->assertEquals($before,$again->fresh()->only(array_keys($before)));
        app(\App\Services\Billing\BillingCompletenessGuard::class)->assertInvoice($again);
    }

    public function test_review_data_missing_seventh_child_detail_is_repaired_without_losing_payments(): void
    {
        Storage::fake('local');
        $facility=$this->facility();$facility->update(['yoyaku_business_id'=>77]);
        $user=User::factory()->create(['name'=>'【テスト】管理者','email'=>'admin-a@example.test','password'=>bcrypt('Demo-local-2026!')]);
        Staff::create(['facility_id'=>$facility->id,'user_id'=>$user->id,'name'=>$user->name,'role'=>'admin','is_active'=>true]);
        require_once base_path('tests/Fixtures/review-data-engine.php');
        require_once base_path('tools/demo-review/repair-billing.php');
        $process=new \Symfony\Component\Process\Process(['python3',base_path('tests/Fixtures/build-review-data.py'),'2026-11-28']);
        $process->mustRun();$spec=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
        reviewSamplesPopulate('u',$spec);
        $period=BillingPeriod::where('year_month','2026-11')->sole();
        try{app(GuardianInvoicePdfService::class)->createInvoicesFromBillingPeriod($period);$this->fail('Incomplete sample data was accepted');}
        catch(ValidationException $error){$this->assertStringContainsString('billing_detail_missing',$error->getMessage());}
        $payments=GuardianInvoice::where('year_month','2026-11')->get()->keyBy('child_id')->map(fn($i)=>$i->only(['paid_amount','paid_at','payment_method','other_charges','notes']));
        $report=repairReviewBillingSamples($facility->id,'2026-11');
        $this->assertSame(11,$report['details']);$this->assertSame(11,$report['invoices']);
        foreach($payments as $childId=>$before){
            $invoice=GuardianInvoice::where('year_month','2026-11')->where('child_id',$childId)->sole();
            $this->assertEquals($before,$invoice->only(array_keys($before)));
        }
        $this->assertSame(11,count(app(GuardianInvoicePdfService::class)->createInvoicesFromBillingPeriod($period->fresh())));
        $invoice=GuardianInvoice::where('year_month','2026-11')->firstOrFail();
        $path=app(GuardianInvoicePdfService::class)->generateInvoice($invoice);
        $this->assertStringStartsWith('%PDF',Storage::disk('local')->get($path));
    }

    public function test_changed_copayment_on_a_paid_invoice_requires_review_without_overwriting_payment(): void
    {
        $period=$this->calculatedPeriod();$service=app(GuardianInvoicePdfService::class);
        $invoice=$service->createInvoicesFromBillingPeriod($period)[0];
        $invoice->update(['payment_status'=>'paid','paid_amount'=>$invoice->total_amount,'paid_at'=>'2026-11-10']);
        $invoice->billingDetail->update(['copayment_cap_applied'=>$invoice->copayment_amount+1]);
        try{$service->createInvoicesFromBillingPeriod($period);$this->fail('Paid invoice was silently changed');}
        catch(ValidationException $error){$this->assertStringContainsString('入金記録',$error->getMessage());}
        $this->assertSame('paid',$invoice->fresh()->payment_status);
        $this->assertSame((int)$invoice->paid_amount,(int)$invoice->fresh()->paid_amount);
        $this->assertSame((int)$invoice->copayment_amount,(int)$invoice->fresh()->copayment_amount);
    }
}
