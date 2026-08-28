<?php

namespace App\Communication;

enum CommunicationRecipientStatus: string
{
    case Eligible = 'eligible';
    case Suppressed = 'suppressed';
}
