<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DataSubjectRequest;
use App\Models\Person;
use App\Models\User;
use App\Privacy\Actions\SubmitDataSubjectRequestAction;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DataSubjectRequestFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_submission_is_private_idempotent_and_audited_once(): void
    {
        $person = Person::factory()->create();
        $actor = User::factory()->create();
        $action = $this->app->make(SubmitDataSubjectRequestAction::class);

        $first = $action->handle($person, DataSubjectRequestType::Export, 'export-1', 'private note', $actor);
        $second = $action->handle($person, DataSubjectRequestType::Export, 'export-1', 'private note', $actor);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(DataSubjectRequestStatus::PendingReview, $first->status);
        $this->assertArrayNotHasKey('request_notes', $first->toArray());
        $this->assertNotSame('private note', $first->getRawOriginal('request_notes'));
        $this->assertSame(1, AuditEvent::query()->where('action', 'privacy.data_subject_request.submitted')->count());
    }

    public function test_export_execution_is_allowed_while_deletion_remains_denied(): void
    {
        $policy = $this->app->make(DataSubjectRequestExecutionPolicy::class);
        $actor = User::factory()->make();

        $exportDecision = $policy->decide(
            DataSubjectRequest::factory()->make(),
            $actor,
        );
        $deletionDecision = $policy->decide(
            DataSubjectRequest::factory()->make(['request_type' => DataSubjectRequestType::Deletion]),
            $actor,
        );

        $this->assertTrue($exportDecision->allowed);
        $this->assertSame('export_execution_allowed', $exportDecision->reasonCode);
        $this->assertFalse($deletionDecision->allowed);
        $this->assertSame('retention_policy_pending', $deletionDecision->reasonCode);
    }
}
