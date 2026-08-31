<?php

namespace App\Press;

enum PressAssetProcessingStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isPublishable(): bool
    {
        return $this === self::Ready;
    }
}
