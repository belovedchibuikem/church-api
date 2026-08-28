<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Models\PressTranslationTransition;
use App\Models\User;
use App\Press\PressTranslationData;
use App\Press\PressTranslationStatus;
use App\Support\Press\CreateMachinePressTranslationAction;
use App\Support\Press\TransitionPressTranslationAction;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class PressTranslationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_machine_translation_requires_each_review_stage_before_approval(): void
    {
        $actor = User::factory()->create();
        $translation = $this->createTranslation($actor);
        $action = $this->app->make(TransitionPressTranslationAction::class);
        Context::add('correlation_id', '9c436a09-ec8b-4a8d-a9aa-df151ecf06b5');

        try {
            $action->handle($translation, PressTranslationStatus::Approved, $actor, 'translation.approved');
            $this->fail('Machine output must never become an approved translation directly.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $translation = $action->handle($translation, PressTranslationStatus::UnderReview, $actor, 'translation.review_started');
        $translation = $action->handle($translation, PressTranslationStatus::Reviewed, $actor, 'translation.review_completed');
        $translation = $action->handle($translation, PressTranslationStatus::Approved, $actor, 'translation.approved');
        $transitionCount = PressTranslationTransition::query()->count();
        $auditCount = AuditEvent::query()->count();
        $retry = $action->handle($translation, PressTranslationStatus::Approved, $actor, 'translation.approval_retry');

        $this->assertSame(PressTranslationStatus::Approved, $retry->status);
        $this->assertNotNull($retry->reviewed_at);
        $this->assertNotNull($retry->approved_at);
        $this->assertSame(3, $transitionCount);
        $this->assertSame($transitionCount, PressTranslationTransition::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
        $this->assertSame(
            '9c436a09-ec8b-4a8d-a9aa-df151ecf06b5',
            PressTranslationTransition::query()->oldest('id')->value('correlation_id'),
        );
    }

    public function test_translation_creation_is_idempotent_and_audit_excludes_translated_text(): void
    {
        $actor = User::factory()->create();
        $publication = PressPublication::factory()->create(['language_code' => 'en']);
        $action = $this->app->make(CreateMachinePressTranslationAction::class);
        $data = $this->translationData();
        $idempotencyKey = 'translation-request-0001';
        $translation = $action->handle($publication, $data, $actor, $idempotencyKey);
        $retry = $action->handle($publication, $data, $actor, $idempotencyKey);

        $this->assertSame($translation->getKey(), $retry->getKey());
        $this->assertSame(1, PressTranslation::query()->count());
        $audit = AuditEvent::query()->where('action', 'press.translation.machine_generated')->sole();
        $this->assertArrayNotHasKey('translated_title', $audit->metadata);
        $this->assertArrayNotHasKey('translated_description', $audit->metadata);
        $this->assertArrayNotHasKey('translated_content', $audit->metadata);

        $this->expectException(DomainException::class);
        $action->handle(
            $publication,
            new PressTranslationData('fr', 'Changed title'),
            $actor,
            $idempotencyKey,
        );
    }

    public function test_source_language_and_duplicate_target_language_are_denied(): void
    {
        $actor = User::factory()->create();
        $publication = PressPublication::factory()->create(['language_code' => 'en']);
        $action = $this->app->make(CreateMachinePressTranslationAction::class);

        try {
            $action->handle(
                $publication,
                new PressTranslationData('en', 'Same language'),
                $actor,
                'translation-source-language',
            );
            $this->fail('Expected a source-language translation to be rejected.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $action->handle($publication, $this->translationData(), $actor, 'translation-first-target');

        $this->expectException(DomainException::class);
        $action->handle(
            $publication,
            new PressTranslationData('fr-FR', 'A second French translation'),
            $actor,
            'translation-second-target',
        );
    }

    public function test_translation_status_and_evidence_are_not_mass_assignable(): void
    {
        $translation = PressTranslation::factory()->create();

        $translation->fill([
            'status' => PressTranslationStatus::Approved->value,
            'target_language_code' => 'de',
            'idempotency_key_hash' => str_repeat('b', 64),
            'approved_at' => now(),
        ]);

        $this->assertSame(PressTranslationStatus::MachineGenerated, $translation->status);
        $this->assertSame('fr', $translation->target_language_code);
        $this->assertNotSame(str_repeat('b', 64), $translation->idempotency_key_hash);
        $this->assertNull($translation->approved_at);
    }

    private function createTranslation(User $actor): PressTranslation
    {
        return $this->app->make(CreateMachinePressTranslationAction::class)->handle(
            PressPublication::factory()->create(['language_code' => 'en']),
            $this->translationData(),
            $actor,
            'translation-request-'.fake()->uuid(),
        );
    }

    private function translationData(): PressTranslationData
    {
        return new PressTranslationData(
            targetLanguageCode: 'fr-FR',
            translatedTitle: 'Titre machine confidentiel',
            translatedSubtitle: 'Sous-titre machine',
            translatedDescription: 'Ce texte ne doit jamais apparaître dans les audits.',
            translatedContent: 'Contenu machine qui exige une révision humaine et théologique.',
        );
    }
}
