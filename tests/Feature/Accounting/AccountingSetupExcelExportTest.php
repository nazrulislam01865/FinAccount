<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class AccountingSetupExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HisebGhorDemoSeeder::class);
        $this->user = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
    }

    public function test_setup_pages_show_excel_export_actions(): void
    {
        foreach (['chart-of-accounts.index', 'transaction-heads.index', 'accounting-rules.index'] as $routeName) {
            $this->actingAs($this->user)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('Export Excel');
        }
    }

    public function test_setup_exports_download_valid_xlsx_archives(): void
    {
        foreach (['chart-of-accounts.export', 'transaction-heads.export', 'accounting-rules.export'] as $routeName) {
            $response = $this->actingAs($this->user)->get(route($routeName));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

            $path = $response->baseResponse->getFile()->getPathname();
            $this->assertFileExists($path);
            $this->assertSame('PK', file_get_contents($path, false, null, 0, 2));
            $this->assertGreaterThan(1000, filesize($path));

            $archive = new PharData($path);
            $this->assertTrue(isset($archive['xl/worksheets/sheet1.xml']));

            $worksheet = $archive['xl/worksheets/sheet1.xml']->getContent();
            $sheetDataPosition = strpos($worksheet, '<sheetData>');
            $autoFilterPosition = strpos($worksheet, '<autoFilter ');
            $mergeCellsPosition = strpos($worksheet, '<mergeCells ');
            $pageMarginsPosition = strpos($worksheet, '<pageMargins ');

            $this->assertNotFalse($sheetDataPosition);
            $this->assertNotFalse($autoFilterPosition);
            $this->assertNotFalse($mergeCellsPosition);
            $this->assertNotFalse($pageMarginsPosition);
            $this->assertStringContainsString('<c ', $worksheet);
            $this->assertTrue($sheetDataPosition < $autoFilterPosition);
            $this->assertTrue($autoFilterPosition < $mergeCellsPosition);
            $this->assertTrue($mergeCellsPosition < $pageMarginsPosition);

            unset($archive);
            @unlink($path);
        }
    }
}
