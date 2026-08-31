<?php

namespace App\Press;

enum PressAssetFormat: string
{
    case Pdf = 'pdf';
    case Epub = 'epub';
    case Audio = 'audio';
    case Video = 'video';
    case Image = 'image';
    case Transcript = 'transcript';
    case Captions = 'captions';
    case Html = 'html';
    case Print = 'print';
}
