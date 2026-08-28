<?php

namespace App\Kca;

enum KcaAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';
}
