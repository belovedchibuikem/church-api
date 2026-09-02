<?php

namespace App\Kca;

final class KcaOrientationStages
{
  /** @var list<string> */
  public const ALL = ['overview', 'rules', 'path', 'mentors'];

  public static function isValid(string $stage): bool
  {
    return in_array($stage, self::ALL, true);
  }
}
