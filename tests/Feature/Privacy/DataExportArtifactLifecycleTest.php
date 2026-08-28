<?php

namespace Tests\Feature\Privacy;

use App\Exceptions\DataExportExecutionDeniedException;
use App\Exceptions\DataExportInvalidStateException;
use App\Files\FileAssetClassification;
use App\Models\DataSubjectRequest;
use App\Models\FileAsset;
use App\Models\User;
use App\Privacy\Actions\BeginDataExportRequestAction;
use App\Privacy\Actions\CompleteDataExportRequestAction;
use App\Privacy\Actions\ExpireDataExportRequestAction;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\ExecutionDecision;
use App\Privacy\PendingDataSubjectRequestExecutionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DataExportArtifactLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_export_processing_is_denied_when_retention_policy_is_pending(): void
    {
        $this->app->instance(
            DataSubjectRequestExecutionPolicy::class,
            new PendingDataSubjectRequestExecutionPolicy,
        );
        $request = DataSubjectRequest::factory()->create();

        $this->expectException(DataExportExecutionDeniedException::class);
        $this->expectExceptionMessage('retention_policy_pending');

        $this->app->make(BeginDataExportRequestAction::class)->handle(
            $request,
            ['profile'],
            User::factory()->create(),
        );
    }

    public function test_private_owned_file_completes_idempotently_and_expires_without_deletion(): void
    {
        $this->allowExecution();
        $actor = User::factory()->create();
        $request = DataSubjectRequest::factory()->create();
        $processing = $this->app->make(BeginDataExportRequestAction::class)->handle(
            $request,
            ['profile', 'consents'],
            $actor,
        );
        $file = FileAsset::factory()->available()->create([
            'owner_person_id' => $processing->person_id,
            'classification' => FileAssetClassification::Confidential,
        ]);
        $expiresAt = now()->addHour();
        $action = $this->app->make(CompleteDataExportRequestAction::class);

        $completed = $action->handle($processing, $file, $expiresAt, $actor);
        $retry = $action->handle($completed, $file, $expiresAt, $actor);

        $this->assertTrue($completed->is($retry));
        $this->assertSame(DataSubjectRequestStatus::Completed, $retry->status);
        $this->assertArrayNotHasKey('data_categories', $retry->toArray());

        $completed->forceFill(['export_expires_at' => now()->subMinute()])->save();
        $expired = $this->app->make(ExpireDataExportRequestAction::class)->handle($completed, $actor);

        $this->assertSame(DataSubjectRequestStatus::Expired, $expired->status);
        $this->assertDatabaseHas('file_assets', ['id' => $file->getKey()]);
    }

    public function test_export_rejects_a_file_owned_by_another_person(): void
    {
        $this->allowExecution();
        $actor = User::factory()->create();
        $processing = $this->app->make(BeginDataExportRequestAction::class)->handle(
            DataSubjectRequest::factory()->create(),
            ['profile'],
            $actor,
        );
        $file = FileAsset::factory()->available()->create([
            'classification' => FileAssetClassification::Restricted,
        ]);

        $this->expectException(DataExportInvalidStateException::class);

        $this->app->make(CompleteDataExportRequestAction::class)->handle(
            $processing,
            $file,
            now()->addHour(),
            $actor,
        );
    }

    private function allowExecution(): void
    {
        $this->app->instance(DataSubjectRequestExecutionPolicy::class, new class implements DataSubjectRequestExecutionPolicy
        {
            public function decide(DataSubjectRequest $request, User $actor): ExecutionDecision
            {
                return new ExecutionDecision(true, 'authorized_scope_approved');
            }
        });
    }
}
