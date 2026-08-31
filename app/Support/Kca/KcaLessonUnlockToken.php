<?php

namespace App\Support\Kca;

use App\Models\KcaEnrollment;
use App\Models\KcaLesson;

class KcaLessonUnlockToken
{
    public function issue(KcaEnrollment $enrollment, KcaLesson $lesson): string
    {
        return hash_hmac('sha256', $this->payload($enrollment, $lesson), $this->key());
    }

    public function matches(KcaEnrollment $enrollment, KcaLesson $lesson, ?string $token): bool
    {
        if ($token === null || $token === '' || strlen($token) !== 64) {
            return false;
        }

        return hash_equals($this->issue($enrollment, $lesson), $token);
    }

    private function payload(KcaEnrollment $enrollment, KcaLesson $lesson): string
    {
        return $enrollment->public_id.'|'.$lesson->public_id.'|'.(int) $lesson->day_index;
    }

    private function key(): string
    {
        $key = config('app.key');

        return is_string($key) && $key !== '' ? $key : 'kca-unlock';
    }
}
