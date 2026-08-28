<?php

namespace App\Press;

enum PressIsbnType: string
{
    case Isbn10 = 'isbn_10';
    case Isbn13 = 'isbn_13';
}
