<?php

namespace App\Reporting;

enum MetricKey: string
{
    case GlobalCountries = 'global.countries';
    case GlobalChurches = 'global.churches';
    case GlobalHomeChurches = 'global.home_churches';
    case GlobalMembers = 'global.members';
    case GlobalKcaStudents = 'global.kca_students';
    case GlobalKcaGraduates = 'global.kca_graduates';
    case GlobalSoulsWon = 'global.souls_won';
    case GlobalMissionProjects = 'global.mission_projects';

    case ChurchMembershipGrowth = 'church.membership_growth';
    case ChurchAttendance = 'church.attendance';
    case ChurchFirstTimerConversion = 'church.first_timer_conversion';
    case ChurchRetention = 'church.retention';
    case ChurchEvangelism = 'church.evangelism';
    case ChurchHomeChurchGrowth = 'church.home_church_growth';

    case MissionSoulsReached = 'mission.souls_reached';
    case MissionSoulsWon = 'mission.souls_won';
    case MissionFollowUpCompletion = 'mission.follow_up_completion';
    case MissionChurchConnection = 'mission.church_connection';
    case MissionCrusades = 'mission.crusades';
    case MissionCountriesReached = 'mission.countries_reached';

    case KcaEnrollment = 'kca.enrollment';
    case KcaCompletion = 'kca.completion';
    case KcaModulePerformance = 'kca.module_performance';
    case KcaMentorEffectiveness = 'kca.mentor_effectiveness';
    case KcaGraduates = 'kca.graduates';
    case KcaActiveChangeAgents = 'kca.active_change_agents';

    case PressPublications = 'press.publications';
    case PressDownloads = 'press.downloads';
    case PressSales = 'press.sales';
    case PressLanguages = 'press.languages';
    case PressReaders = 'press.readers';
    case BibleReadersDay = 'bible.readers_day';
    case BibleReadersWeek = 'bible.readers_week';
    case BibleReadersYear = 'bible.readers_year';
}
