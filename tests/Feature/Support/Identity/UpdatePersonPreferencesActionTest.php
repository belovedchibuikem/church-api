<?php

namespace Tests\Feature\Support\Identity;

use App\Models\AuditEvent;
use App\Models\Person;
use App\Models\PersonPreference;
use App\Models\User;
use App\Support\Identity\UpdatePersonPreferencesAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdatePersonPreferencesActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_provider_neutral_preferences_for_the_canonical_person(): void
    {
        $actor = User::factory()->create();
        $person = Person::factory()->create();

        $preference = $this->app->make(UpdatePersonPreferencesAction::class)->handle(
            $person,
            'en-NG',
            'Africa/Lagos',
            ['email', 'in_app', 'email'],
            $actor,
        );

        $this->assertModelExists($preference);
        $this->assertTrue(Str::isUlid($preference->public_id));
        $this->assertSame($person->getKey(), $preference->person_id);
        $this->assertSame('en-NG', $preference->locale);
        $this->assertSame('Africa/Lagos', $preference->timezone);
        $this->assertSame(['email', 'in_app'], $preference->notification_channels);

        $auditEvent = AuditEvent::query()->sole();
        $this->assertSame('identity.preferences.updated', $auditEvent->action);
        $this->assertSame(
            ['changed_fields' => ['locale', 'timezone', 'notification_channels']],
            $auditEvent->metadata,
        );
    }

    public function test_updates_existing_preferences_and_is_idempotent_when_unchanged(): void
    {
        $person = Person::factory()->create();
        $action = $this->app->make(UpdatePersonPreferencesAction::class);
        $action->handle($person, 'en', 'UTC', ['in_app']);

        $preference = $action->handle($person, 'fr', 'Africa/Lagos', ['email']);
        $unchangedPreference = $action->handle($person, 'fr', 'Africa/Lagos', ['email']);

        $this->assertSame($preference->getKey(), $unchangedPreference->getKey());
        $this->assertSame(1, PersonPreference::query()->count());
        $this->assertSame('fr', $unchangedPreference->locale);
        $this->assertSame('Africa/Lagos', $unchangedPreference->timezone);
        $this->assertSame(['email'], $unchangedPreference->notification_channels);
        $this->assertSame(2, AuditEvent::query()->count());
    }

    #[DataProvider('invalidPreferences')]
    public function test_rejects_invalid_preferences_without_writing_records(
        string $locale,
        string $timezone,
        array $notificationChannels,
    ): void {
        $person = Person::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(UpdatePersonPreferencesAction::class)->handle(
                $person,
                $locale,
                $timezone,
                $notificationChannels,
            );
            $this->fail('Expected the invalid preferences to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, PersonPreference::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * @return array<string, array{string, string, array<int, string>}>
     */
    public static function invalidPreferences(): array
    {
        return [
            'invalid locale' => ['en_@@', 'UTC', ['email']],
            'unknown timezone' => ['en', 'Unknown/Timezone', ['email']],
            'free-form channel' => ['en', 'UTC', ['Email Address']],
            'too many channels' => ['en', 'UTC', array_fill(0, 21, 'email')],
        ];
    }
}
