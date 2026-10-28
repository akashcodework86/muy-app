<?php

namespace Tests\Unit;

use App\Services\HubBatchImportParser;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class HubBatchImportParserTest extends TestCase
{
    public function test_it_finds_headers_on_second_row_and_flags_duplicate_app_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hub-import-');
        file_put_contents($path, "Chamoli batch list\nSN,Name,Mobile,Application no\n1,Anita,9876543210,90C0000C\n2,Anita Two,9876543211,90C0000C\n");
        $file = new UploadedFile($path, 'Onboard.csv', 'text/csv', null, true);

        $parsed = (new HubBatchImportParser)->parse($file);

        $this->assertSame(2, $parsed['header_row']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertFalse($parsed['rows'][0]['duplicate_app_no_in_file']);
        $this->assertTrue($parsed['rows'][1]['duplicate_app_no_in_file']);
        @unlink($path);
    }
}
