<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DemoDownloadProbeTest extends TestCase
{
    public function test_a_real_download_larger_than_remaining_memory_is_verified_without_buffering_it(): void
    {
        $process=new Process([PHP_BINARY,'-d','memory_limit=128M',dirname(__DIR__).'/Fixtures/download-probe-check.php']);
        $process->mustRun();
        $report=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(64*1024*1024,$report['stream_bytes']);
        $this->assertLessThan(100*1024*1024,$report['peak_bytes']);
        $this->assertSame('128M',$report['memory_limit']);
        $this->assertTrue($report['invalid_rejected']);$this->assertTrue($report['exception_cleaned']);
    }
}
