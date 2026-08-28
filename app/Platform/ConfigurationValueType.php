<?php

namespace App\Platform;

enum ConfigurationValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
}
