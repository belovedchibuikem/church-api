<?php

namespace App\Support\Communication;

use App\Communication\CommunicationChannel;
use App\Models\Person;
use App\Models\PersonConsent;
use App\Models\PersonPreference;
use App\Support\Communication\Contracts\CommunicationConsentPolicy;

class DatabaseCommunicationConsentPolicy implements CommunicationConsentPolicy
{
    public function decide(
        Person $person,
        CommunicationChannel $channel,
        CommunicationPurpose $purpose,
    ): CommunicationConsentDecision {
        $hasConsent = PersonConsent::query()
            ->whereBelongsTo($person)
            ->where('purpose', $purpose->value)
            ->whereNull('withdrawn_at')
            ->exists();

        if (! $hasConsent) {
            return CommunicationConsentDecision::deny('consent_missing_or_withdrawn');
        }

        $preference = PersonPreference::query()
            ->whereBelongsTo($person)
            ->first(['notification_channels']);
        $channels = $preference?->notification_channels;

        if (! is_array($channels) || ! in_array($channel->value, $channels, true)) {
            return CommunicationConsentDecision::deny('channel_preference_disabled');
        }

        return CommunicationConsentDecision::allow();
    }
}
