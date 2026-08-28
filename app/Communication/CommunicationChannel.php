<?php

namespace App\Communication;

enum CommunicationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Push = 'push';
}
