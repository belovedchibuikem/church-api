<?php

namespace App\Communication;

enum CommunicationAudienceRuleType: string
{
    case AllUsers = 'all_users';
    case Scope = 'scope';
    case Church = 'church';
    case HomeChurch = 'home_church';
    case Department = 'department';
    case KcaCohort = 'kca_cohort';
    case Role = 'role';
    case Permission = 'permission';
}
