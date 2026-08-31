<?php

namespace App\Support\Communication;

use Illuminate\Support\Str;

final class CommunicationCopy
{
    public static function purposeLabel(string $purpose): string
    {
        $tail = preg_replace('/\Acommunications\./', '', trim($purpose)) ?? trim($purpose);
        $human = str_replace(['_', '-', '.'], ' ', $tail);

        return $human === '' ? 'Ministry update' : Str::title($human);
    }

    public static function channelLabel(string $channel): string
    {
        return match (strtolower($channel)) {
            'in_app' => 'In-App',
            'email' => 'Email',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'push' => 'Push',
            default => Str::title(str_replace(['_', '-'], ' ', $channel)),
        };
    }

    public static function campaignTitle(?string $subject, string $purpose): string
    {
        $subject = trim((string) $subject);

        return $subject !== '' ? $subject : self::purposeLabel($purpose);
    }
}
