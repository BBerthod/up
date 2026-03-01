<?php

namespace Tests\Feature\Services;

use App\Enums\FunctionalCheckStatus;
use App\Enums\FunctionalCheckType;
use App\Models\FunctionalCheck;
use App\Services\FunctionalCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FunctionalCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private FunctionalCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FunctionalCheckService::class);
    }

    public function test_run_creates_passed_result(): void
    {
        $check = FunctionalCheck::factory()->create([
            'type' => FunctionalCheckType::CONTENT,
            'url' => 'https://example.com',
            'rules' => [['type' => 'text_present', 'value' => 'Hello']],
        ]);

        Http::fake(['example.com' => Http::response('<p>Hello World</p>', 200)]);

        $result = $this->service->run($check);

        $this->assertSame(FunctionalCheckStatus::PASSED, $result->status);
        $this->assertDatabaseHas('functional_check_results', [
            'functional_check_id' => $check->id,
            'status' => 'passed',
        ]);
    }

    public function test_run_creates_failed_result(): void
    {
        $check = FunctionalCheck::factory()->create([
            'type' => FunctionalCheckType::CONTENT,
            'url' => 'https://example.com',
            'rules' => [['type' => 'text_present', 'value' => 'Hello']],
        ]);

        Http::fake(['example.com' => Http::response('<p>Nothing</p>', 200)]);

        $result = $this->service->run($check);

        $this->assertSame(FunctionalCheckStatus::FAILED, $result->status);
    }

    public function test_run_updates_last_checked_at(): void
    {
        $check = FunctionalCheck::factory()->create([
            'type' => FunctionalCheckType::CONTENT,
            'url' => 'https://example.com',
            'rules' => [],
        ]);

        Http::fake(['example.com' => Http::response('ok', 200)]);

        $this->service->run($check);

        $this->assertNotNull($check->fresh()->last_checked_at);
    }

    public function test_run_updates_last_status(): void
    {
        $check = FunctionalCheck::factory()->create([
            'type' => FunctionalCheckType::CONTENT,
            'url' => 'https://example.com',
            'rules' => [['type' => 'text_absent', 'value' => 'Fatal error']],
        ]);

        Http::fake(['example.com' => Http::response('Fatal error: oops', 500)]);

        $this->service->run($check);

        $this->assertSame(FunctionalCheckStatus::FAILED, $check->fresh()->last_status);
    }
}
