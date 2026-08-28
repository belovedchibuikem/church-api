<?php

namespace App\Events;

enum EventRegistrationStatus: string
{
    case PaymentPending = 'payment_pending';
    case Confirmed = 'confirmed';
    case Attended = 'attended';
    case FeedbackRecorded = 'feedback_recorded';
    case Cancelled = 'cancelled';
}
