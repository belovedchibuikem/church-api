<?php

namespace App\Press;

enum PressPublicationFormat: string
{
    case Print = 'print';
    case Pdf = 'pdf';
    case Epub = 'epub';
    case Audio = 'audio';
    case Video = 'video';
}
