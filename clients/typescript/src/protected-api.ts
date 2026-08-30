// Generated from openapi/protected-v1.openapi.json (SHA-256: ac99484ebb77fdc19e1ff81bbc49914584022c220576d56f1cc518487437318d).
// Do not edit directly. Run: php scripts/generate-protected-api.php

export type JsonPrimitive = string | number | boolean | null;
export type JsonValue = JsonPrimitive | JsonObject | JsonValue[];
export interface JsonObject { [key: string]: JsonValue; }
export interface SuccessEnvelope<T> { data: T; meta: JsonObject; correlation_id: string; }
export interface ErrorEnvelope { error: { code: string; message: string; details: JsonObject }; meta: JsonObject; correlation_id: string; }
export interface ProtectedRequestOptions {
  correlationId?: string;
  query?: Record<string, string | number | boolean | undefined>;
  csrfToken?: string;
  bearerToken?: string;
  deviceIdentifier?: string;
  scope?: { type: string; id: string };
  signal?: AbortSignal;
}

export interface AdminScope { type: string; id: string; }
export interface AdminCountry { id: string; iso_code: string; name: string; created_at: string | null; }
export type AdminCountryList = AdminCountry[];
export interface CreateAdminCountryInput { iso_code: string; name: string; }
export interface AdminAdministrativeLevel { id: string; country_id: string; code: string; name: string; sort_order: number; }
export type AdminAdministrativeLevelList = AdminAdministrativeLevel[];
export interface CreateAdminAdministrativeLevelInput { code: string; name: string; sort_order: number; }
export interface AdminAdministrativeUnit {
  id: string; name: string; reference_code: string | null; country: JsonObject;
  administrative_level: JsonObject; parent: JsonObject | null; created_at: string | null;
}
export type AdminAdministrativeUnitList = AdminAdministrativeUnit[];
export interface CreateAdminAdministrativeUnitInput {
  country_id: string; administrative_level_id: string; name: string; parent_id?: string | null; reference_code?: string | null;
}
export interface MoveAdminAdministrativeUnitInput { parent_id: string | null; }
export interface AdminLocation {
  id: string; name: string; country: JsonObject; administrative_unit: JsonObject | null;
  address: JsonObject; timezone: string; coordinates: JsonObject | null; created_at: string | null;
}
export type AdminLocationList = AdminLocation[];
export interface CreateAdminLocationInput {
  country_id: string; name: string; timezone: string; administrative_unit_id?: string | null;
  address_line_one?: string | null; address_line_two?: string | null; locality?: string | null;
  postal_code?: string | null; latitude?: number | null; longitude?: number | null;
}
export interface AdminPlatformConfiguration {
  id: string; key: string; value_type: 'string' | 'integer' | 'boolean' | 'json';
  classification: 'internal' | 'confidential'; environment: string; scope: AdminScope | null;
  value: JsonValue; has_value: boolean; updated_at: string | null;
}
export type AdminPlatformConfigurationList = AdminPlatformConfiguration[];
export interface UpsertAdminPlatformConfigurationInput {
  key: string; value_type: 'string' | 'integer' | 'boolean' | 'json';
  classification: 'internal' | 'confidential'; value: JsonValue; environment: string;
  scope_type?: string | null; scope_id?: string | null;
}
export interface AdminFeatureFlag {
  id: string; key: string; environment: string; scope: AdminScope | null; enabled: boolean;
  rollout_percentage: number; starts_at: string | null; ends_at: string | null; updated_at: string | null;
}
export type AdminFeatureFlagList = AdminFeatureFlag[];
export interface UpsertAdminFeatureFlagInput {
  key: string; environment: string; rollout_percentage: number; scope_type?: string | null;
  scope_id?: string | null; starts_at?: string | null; ends_at?: string | null;
}
export interface AdminObjectStorageStatus {
  provider: 's3'; configured: boolean; active: boolean; credentials_configured: boolean;
  active_provider?: 'local' | 's3'; region?: string | null; bucket?: string | null;
  endpoint?: string | null; url?: string | null; root_prefix?: string | null;
  use_path_style_endpoint?: boolean; configuration_revision?: number;
  validation?: JsonObject; validation_result?: JsonObject; activated_at?: string | null;
}
export interface ConfigureAdminObjectStorageInput {
  access_key_id: string; secret_access_key: string; region: string; bucket: string;
  endpoint?: string | null; url?: string | null; root_prefix?: string | null;
  use_path_style_endpoint?: boolean;
}
export interface AdminObjectStorageDeactivation { active_provider: 'local'; object_storage_active: false; }
export interface AdminMapsProviderStatus {
  configured: boolean; active: boolean; active_provider: 'google' | 'mapbox' | 'leaflet';
  providers: JsonObject; default_center?: { latitude: number; longitude: number };
  default_zoom?: number; configuration_revision?: number; validation?: JsonObject;
  activated_at?: string | null;
}
export interface ConfigureAdminMapsProviderInput {
  active_provider: 'google' | 'mapbox' | 'leaflet';
  google_api_key?: string | null; mapbox_access_token?: string | null;
  leaflet_tile_url?: string | null; default_latitude?: number | null;
  default_longitude?: number | null; default_zoom?: number | null;
}
export interface AdminMapsProviderDeactivation {
  active: false; active_provider: 'google' | 'mapbox' | 'leaflet';
}
export interface UserCapabilities {
  permissions: string[];
  scopes: Array<{ type: string; key: string }>;
}
export interface CheckUserAuthorizationInput {
  permission: string; scope_type?: string | null; scope_id?: string | null; resource_id?: string | null;
}
export interface UserAuthorizationDecision {
  allowed: boolean; state: 'allowed' | 'forbidden'; permission: string;
  canonical_permission: string; reason: string;
  scope?: { type: string; id: string }; decision_id?: string | null;
}
export interface ProtectedDomainRecord { id: string; [key: string]: JsonValue; }
export type ProtectedDomainRecordList = ProtectedDomainRecord[];
export interface AdminSearchResult {
  resource_type: string; resource_id: string; title: string;
  summary?: string | null; classification: string; metadata?: JsonObject;
}
export type AdminSearchResults = AdminSearchResult[];
export interface AdminAdvisoryResponse {
  available: boolean; recommendation: string | null; reason_code: string;
  requires_human_decision: boolean; metadata: JsonObject;
}
export interface QueryAdminSearchInput { term: string; resource_types?: string[]; limit?: number; }
export interface RequestAdminAdvisoryInput {
  assistant: string; use_case: string; instruction: string; context?: JsonObject;
}
export interface TransitionWithReasonInput { status: string; reason_code?: string | null; }
export interface TransitionStatusInput { status: string; }
export interface ReasonCodeInput { reason_code: string; }
export interface IdempotencyKeyInput { idempotency_key?: string; }
export interface CreateAdminChurchInput { name: string; location_id: string; administrative_unit_id: string; }
export interface CreateAdminHomeChurchApplicationInput {
  applicant_person_id: string; church_id: string; location_id: string; administrative_unit_id: string;
  proposed_name: string; expected_participants: number; meeting_day: string; meeting_time: string;
  contact_email: string; contact_phone: string; guidelines_agreed_at: string;
}
export interface CreateAdminFirstTimerInput {
  person_id: string; church_id: string; home_church_id?: string | null;
  assigned_follow_up_person_id?: string | null; registered_at?: string | null;
}
export interface StartAdminChurchMembershipInput {
  person_id: string; church_id: string; home_church_id?: string | null; joined_at?: string | null;
}
export interface CaptureAdminMissionSoulInput {
  person_id?: string | null; given_name?: string | null; family_name?: string | null;
  middle_name?: string | null; preferred_name?: string | null;
}
export interface AssignAdminMissionSoulMentorInput { mission_team_assignment_id: string; }
export interface RecordAdminMissionSoulFollowUpInput {
  mentor_assignment_id: string; channel_code: string; outcome_code: string; occurred_at: string;
}
export interface CreateAdminMissionInvitationInput {
  crusade_id: string; requester_person_id: string; requested_location_id: string;
}
export interface EnrollAdminKcaStudentInput { cohort_id: string; registration_number: string; starts_on: string; }
export interface SubmitAdminKcaEvidenceInput {
  enrollment_id: string; file_asset_id: string; submitted_by_person_id: string;
}
export interface ReviewAdminKcaEvidenceInput { reviewer_person_id: string; outcome: string; }
export interface IssueAdminKcaCertificateInput {
  certificate_number: string; completion_on: string; verification_code: string;
}
export interface CreateAdminKcaYearInput { code: string; name: string; starts_on: string; ends_on: string; }
export interface CreateAdminKcaCohortInput { code: string; name: string; starts_on: string; ends_on: string; }
export interface CreateAdminKcaModuleInput { code: string; title: string; sequence: number; }
export interface CreateAdminKcaLessonInput { code: string; title: string; sequence: number; }
export interface RecordAdminKcaAttendanceInput { lesson_id: string; status: string; session_on: string; }
export interface CreateAdminPressPublicationInput {
  title: string; publisher_name: string; language_code: string; format: string;
  subtitle?: string | null; edition?: string | null; publication_date?: string | null;
  copyright_year?: number | null; page_count?: number | null; category?: string | null;
  description?: string | null; cover_file_asset_id?: string | null; content_file_asset_id?: string | null;
  price_minor?: number | null; currency_code?: string | null;
}
export interface AssignAdminPressPublicationIsbnInput { isbn: string; reason_code: string; }
export interface AddAdminPressPublicationContributorInput { person_id: string; role: string; }
export interface CreateAdminPressTranslationInput {
  target_language_code: string; translated_title: string;
  translated_subtitle?: string | null; translated_description?: string | null; translated_content?: string | null;
}
export interface CreateAdminMinistryEventInput {
  category_code: string; name: string; starts_at: string; ends_at: string;
  location_id?: string | null; registration_opens_at?: string | null; registration_closes_at?: string | null;
  fee_amount_minor?: number | null; fee_currency?: string | null; capacity?: number | null; published_at?: string | null;
}
export interface RegisterAdminEventRegistrationInput { person_id: string; }
export interface RecordAdminEventAttendanceInput { source_code: string; }
export interface RecordAdminEventFeedbackInput { rating: number; }
export interface CreateAdminPaymentIntentInput { event_registration_id: string; }
export interface RequestAdminPaymentRefundInput { amount_minor: number; reason_code: string; }
export interface CreateAdminCommunicationTemplateInput {
  code: string; channel: string; locale: string; subject: string; body: string;
}
export interface CreateAdminCommunicationAudienceInput {
  code: string; name: string; rules: JsonObject[];
}
export interface PrepareAdminCommunicationBroadcastInput {
  template_id: string; audience_id: string; kind: string; channel: string; purpose: string; scheduled_at?: string | null;
}
export interface CreateAdminAlertRuleInput {
  code: string; title: string; condition_type: string; severity: string; configuration: JsonObject;
  scope_type?: string | null; scope_key?: string | null;
}
export interface SetAdminAlertRuleEnabledInput { enabled: boolean; }
export interface EvaluateAdminAlertRuleInput {
  condition_reference_type: string; condition_reference_key: string; summary?: string | null;
  facts?: JsonObject; scope_type?: string | null; scope_key?: string | null;
}
export interface SubmitAdminDataSubjectRequestInput { person_id: string; request_type: string; notes?: string | null; }
export interface BeginAdminDataExportInput {
  data_categories: string[]; scope_type?: string | null; scope_key?: string | null;
}
export interface CompleteAdminDataExportInput { file_asset_id: string; expires_at: string; }
export interface ReportAdminSafeguardingIncidentInput {
  concern_type: string; severity: string; restricted_summary: string;
  subject_person_id?: string | null; occurred_at?: string | null;
}
export interface RegisterAdminGuardianRelationshipInput {
  guardian_person_id: string; child_person_id: string; relationship_type: string;
}
export interface StoreAdminFileAssetInput { purpose: string; classification: string; owner_person_id?: string | null; [key: string]: JsonValue | undefined; }
export interface AssignAdminUserRoleInput { role_id: string; expires_at?: string | null; }
export interface AssignAdminRoleAssignmentScopeInput { scope_type: string; scope_key: string; }
export interface GrantAdminRolePermissionInput { permission_id: string; }
export interface SuspendAdminUserInput { reason: string; }
export interface CreateAdminContentPageInput {
  slug: string; title: string; body: string; summary?: string | null; locale?: string; published_at?: string | null;
}
export interface UpdateAdminContentPageInput {
  slug?: string; title?: string; body?: string; summary?: string | null; locale?: string; published_at?: string | null;
}
export interface CreateAdminContentPageItemInput {
  kind: string; title: string; body: string; meta?: JsonObject; href?: string | null;
  sort_order?: number; published_at?: string | null;
}
export interface GrantUserConsentInput { purpose: string; policy_version: string; }
export interface UpdateUserPreferencesInput { locale: string; timezone: string; notification_channels: string[]; }
export interface CreateUserGivingPaymentIntentInput { amount_minor: number; currency: string; }
export interface CreateUserPrayerRequestInput { subject: string; body: string; }
export interface CreateUserPastoralNeedInput { category: string; summary: string; }
export interface CreateUserMessageConversationInput {
  participant_person_ids: string[]; first_message: string; subject?: string | null;
}
export interface CreateUserConversationMessageInput { body: string; }
export interface UpdateUserSyncCheckpointInput { cursor: string; }
export interface RegisterUserEventRegistrationInput { idempotency_key?: string; }
export interface RecordUserEventFeedbackInput { rating: number; registration_id: string; }
export interface SubmitUserDataSubjectRequestInput { request_type: string; notes?: string | null; }
export interface StoreUserFileAssetInput { purpose: string; classification: string; [key: string]: JsonValue; }
export interface StartUserChurchMembershipInput { home_church_id?: string | null; }
export interface SubmitUserHomeChurchReportInput { summary: string; period_code?: string | null; }
export interface UserHomeChurchReportSubmission { id: string; status: 'submitted'; submitted_at: string; }

export class ProtectedApiError extends Error {
  public constructor(public readonly status: number, public readonly envelope: ErrorEnvelope) {
    super(envelope.error.message);
    this.name = 'ProtectedApiError';
  }
}

export class FamilyHouseProtectedApiClient {
  public constructor(private readonly baseUrl: string, private readonly fetcher: typeof fetch = globalThis.fetch) {}

  public listAdminPermissions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/access/permissions', options);
  }

  public listAdminRoles(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/access/roles', options);
  }

  public listAdminScopeAssignments(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/access/scope-assignments', options);
  }

  public listAdminAuditEvents(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/security/audit-events', options);
  }

  public listAdminAccessDecisions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/security/access-decisions', options);
  }

  public listAdminCountries(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminCountryList>> {
    return this.request<AdminCountryList>('GET', '/api/v1/admin/organization/countries', options);
  }

  public createAdminCountry(body: CreateAdminCountryInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminCountry>> {
    return this.request<AdminCountry>('POST', '/api/v1/admin/organization/countries', options, body as unknown as JsonObject);
  }

  public listAdminAdministrativeLevels(country: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdministrativeLevelList>> {
    return this.request<AdminAdministrativeLevelList>('GET', `/api/v1/admin/organization/countries/${encodeURIComponent(country)}/levels`, options);
  }

  public createAdminAdministrativeLevel(country: string, body: CreateAdminAdministrativeLevelInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdministrativeLevel>> {
    return this.request<AdminAdministrativeLevel>('POST', `/api/v1/admin/organization/countries/${encodeURIComponent(country)}/levels`, options, body as unknown as JsonObject);
  }

  public listAdminLocations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminLocationList>> {
    return this.request<AdminLocationList>('GET', '/api/v1/admin/organization/locations', options);
  }

  public createAdminLocation(body: CreateAdminLocationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminLocation>> {
    return this.request<AdminLocation>('POST', '/api/v1/admin/organization/locations', options, body as unknown as JsonObject);
  }

  public listAdminAdministrativeUnits(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdministrativeUnitList>> {
    return this.request<AdminAdministrativeUnitList>('GET', '/api/v1/admin/organization/units', options);
  }

  public createAdminAdministrativeUnit(body: CreateAdminAdministrativeUnitInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdministrativeUnit>> {
    return this.request<AdminAdministrativeUnit>('POST', '/api/v1/admin/organization/units', options, body as unknown as JsonObject);
  }

  public moveAdminAdministrativeUnit(unit: string, body: MoveAdminAdministrativeUnitInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdministrativeUnit>> {
    return this.request<AdminAdministrativeUnit>('PATCH', `/api/v1/admin/organization/units/${encodeURIComponent(unit)}/parent`, options, body as unknown as JsonObject);
  }

  public listAdminPlatformConfigurations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminPlatformConfigurationList>> {
    return this.request<AdminPlatformConfigurationList>('GET', '/api/v1/admin/platform/configurations', options);
  }

  public upsertAdminPlatformConfiguration(body: UpsertAdminPlatformConfigurationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminPlatformConfiguration>> {
    return this.request<AdminPlatformConfiguration>('PUT', '/api/v1/admin/platform/configurations', options, body as unknown as JsonObject);
  }

  public listAdminFeatureFlags(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminFeatureFlagList>> {
    return this.request<AdminFeatureFlagList>('GET', '/api/v1/admin/platform/feature-flags', options);
  }

  public upsertAdminFeatureFlag(body: UpsertAdminFeatureFlagInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminFeatureFlag>> {
    return this.request<AdminFeatureFlag>('PUT', '/api/v1/admin/platform/feature-flags', options, body as unknown as JsonObject);
  }

  public enableAdminFeatureFlag(featureFlag: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminFeatureFlag>> {
    return this.request<AdminFeatureFlag>('POST', `/api/v1/admin/platform/feature-flags/${encodeURIComponent(featureFlag)}/enabled`, options);
  }

  public disableAdminFeatureFlag(featureFlag: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminFeatureFlag>> {
    return this.request<AdminFeatureFlag>('DELETE', `/api/v1/admin/platform/feature-flags/${encodeURIComponent(featureFlag)}/enabled`, options);
  }

  public getAdminObjectStorage(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminObjectStorageStatus>> {
    return this.request<AdminObjectStorageStatus>('GET', '/api/v1/admin/platform/storage/object-storage', options);
  }

  public configureAdminObjectStorage(body: ConfigureAdminObjectStorageInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminObjectStorageStatus>> {
    return this.request<AdminObjectStorageStatus>('PUT', '/api/v1/admin/platform/storage/object-storage', options, body as unknown as JsonObject);
  }

  public activateAdminObjectStorage(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminObjectStorageStatus>> {
    return this.request<AdminObjectStorageStatus>('POST', '/api/v1/admin/platform/storage/object-storage/activation', options);
  }

  public deactivateAdminObjectStorage(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminObjectStorageDeactivation>> {
    return this.request<AdminObjectStorageDeactivation>('DELETE', '/api/v1/admin/platform/storage/object-storage/activation', options);
  }

  public validateAdminObjectStorage(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminObjectStorageStatus>> {
    return this.request<AdminObjectStorageStatus>('POST', '/api/v1/admin/platform/storage/object-storage/validation', options);
  }

  public getAdminMapsProvider(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminMapsProviderStatus>> {
    return this.request<AdminMapsProviderStatus>('GET', '/api/v1/admin/platform/maps', options);
  }

  public configureAdminMapsProvider(body: ConfigureAdminMapsProviderInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminMapsProviderStatus>> {
    return this.request<AdminMapsProviderStatus>('PUT', '/api/v1/admin/platform/maps', options, body as unknown as JsonObject);
  }

  public activateAdminMapsProvider(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminMapsProviderStatus>> {
    return this.request<AdminMapsProviderStatus>('POST', '/api/v1/admin/platform/maps/activation', options, body as unknown as JsonObject);
  }

  public deactivateAdminMapsProvider(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminMapsProviderDeactivation>> {
    return this.request<AdminMapsProviderDeactivation>('DELETE', '/api/v1/admin/platform/maps/activation', options);
  }

  public listAdminContentPages(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/content/pages', options);
  }

  public createAdminContentPage(body: CreateAdminContentPageInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/content/pages', options, body as unknown as JsonObject);
  }

  public updateAdminContentPage(page: string, body: UpdateAdminContentPageInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('PUT', `/api/v1/admin/content/pages/${encodeURIComponent(page)}`, options, body as unknown as JsonObject);
  }

  public createAdminContentPageItem(page: string, body: CreateAdminContentPageItemInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/content/pages/${encodeURIComponent(page)}/items`, options, body as unknown as JsonObject);
  }

  public listAdminChurches(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/church/churches', options);
  }

  public createAdminChurch(body: CreateAdminChurchInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/church/churches', options, body as unknown as JsonObject);
  }

  public listAdminHomeChurches(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/church/home-churches', options);
  }

  public listAdminHomeChurchApplications(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/church/home-church-applications', options);
  }

  public createAdminHomeChurchApplication(body: CreateAdminHomeChurchApplicationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/church/home-church-applications', options, body as unknown as JsonObject);
  }

  public transitionAdminHomeChurchApplication(application: string, body: TransitionWithReasonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/church/home-church-applications/${encodeURIComponent(application)}/transitions`, options, body as unknown as JsonObject);
  }

  public listAdminFirstTimers(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/church/first-timers', options);
  }

  public createAdminFirstTimer(body: CreateAdminFirstTimerInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/church/first-timers', options, body as unknown as JsonObject);
  }

  public listAdminChurchFollowUpTasks(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/church/follow-up-tasks', options);
  }

  public completeAdminChurchFollowUpTask(task: string, body: ReasonCodeInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/church/follow-up-tasks/${encodeURIComponent(task)}/completion`, options, body as unknown as JsonObject);
  }

  public startAdminChurchMembership(body: StartAdminChurchMembershipInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/church/memberships', options, body as unknown as JsonObject);
  }

  public endAdminChurchMembership(membership: string, body: ReasonCodeInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/church/memberships/${encodeURIComponent(membership)}/end`, options, body as unknown as JsonObject);
  }

  public listAdminMissionCrusades(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/mission/crusades', options);
  }

  public listAdminMissionSouls(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/mission/souls', options);
  }

  public captureAdminMissionSoul(crusade: string, body: CaptureAdminMissionSoulInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/mission/crusades/${encodeURIComponent(crusade)}/souls`, options, body as unknown as JsonObject);
  }

  public assignAdminMissionSoulMentor(soul: string, body: AssignAdminMissionSoulMentorInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/mission/souls/${encodeURIComponent(soul)}/mentor-assignment`, options, body as unknown as JsonObject);
  }

  public recordAdminMissionSoulFollowUp(soul: string, body: RecordAdminMissionSoulFollowUpInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/mission/souls/${encodeURIComponent(soul)}/follow-ups`, options, body as unknown as JsonObject);
  }

  public completeAdminMissionSoulFollowUp(soul: string, body: ReasonCodeInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/mission/souls/${encodeURIComponent(soul)}/follow-up-completion`, options, body as unknown as JsonObject);
  }

  public transitionAdminMissionInvitation(invitation: string, body: TransitionWithReasonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/mission/invitations/${encodeURIComponent(invitation)}/transitions`, options, body as unknown as JsonObject);
  }

  public listAdminMissionInvitations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecordList>> {
    return this.request<ProtectedDomainRecordList>('GET', '/api/v1/admin/mission/invitations', options);
  }

  public createAdminMissionInvitation(body: CreateAdminMissionInvitationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/mission/invitations', options, body as unknown as JsonObject);
  }

  public listAdminCatalogKcaApplications(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/kca/applications', options);
  }

  public listAdminCatalogKcaEnrollments(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/kca/enrollments', options);
  }

  public listAdminCatalogKcaEvidenceSubmissions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/kca/evidence-submissions', options);
  }

  public listAdminCatalogKcaAssessmentResults(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/kca/assessment-results', options);
  }

  public listAdminCatalogKcaCertificates(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/kca/certificates', options);
  }

  public listAdminCatalogPressPublications(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/press/publications', options);
  }

  public listAdminCatalogPressTranslations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/press/translations', options);
  }

  public listAdminCatalogEvents(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/events/events', options);
  }

  public listAdminCatalogEventRegistrations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/events/registrations', options);
  }

  public listAdminCatalogPaymentIntents(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-intents', options);
  }

  public listAdminCatalogPaymentTransactions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-transactions', options);
  }

  public listAdminCatalogPaymentReconciliations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-reconciliations', options);
  }

  public listAdminCatalogPaymentReceipts(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-receipts', options);
  }

  public listAdminCatalogPaymentRefunds(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-refunds', options);
  }

  public listAdminCatalogPaymentDisputes(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/finance/payment-disputes', options);
  }

  public listAdminCatalogCommunicationTemplates(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/communications/templates', options);
  }

  public listAdminCatalogCommunicationAudiences(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/communications/audiences', options);
  }

  public listAdminCatalogCommunicationBroadcasts(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/communications/broadcasts', options);
  }

  public listAdminCatalogCommunicationDeliveries(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/communications/delivery-attempts', options);
  }

  public listAdminCatalogCommunicationNotifications(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/communications/notifications', options);
  }

  public listAdminCatalogAlertRules(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/reporting/alert-rules', options);
  }

  public listAdminCatalogAlertOccurrences(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/reporting/alert-occurrences', options);
  }

  public listAdminCatalogDataSubjectRequests(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/privacy/data-subject-requests', options);
  }

  public listAdminCatalogFileAssets(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/catalog/platform/files', options);
  }

  public listAdminUsers(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/admin/users', options);
  }

  public getAdminUser(user: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/admin/users/${encodeURIComponent(user)}`, options);
  }

  public suspendAdminUser(user: string, body: SuspendAdminUserInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/users/${encodeURIComponent(user)}/suspension`, options, body as unknown as JsonObject);
  }

  public reactivateAdminUser(user: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('DELETE', `/api/v1/admin/users/${encodeURIComponent(user)}/suspension`, options);
  }

  public getBrowserCsrfCookie(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/auth/csrf-cookie', options);
  }

  public sendEmailVerification(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/email/verification-notification', options);
  }

  public verifyEmail(id: string, hash: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`, options);
  }

  public browserLogin(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/login', options, body as unknown as JsonObject);
  }

  public browserLogout(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/logout', options);
  }

  public browserMfaChallenge(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/mfa/challenge', options, body as unknown as JsonObject);
  }

  public browserMfaConfirm(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/mfa/totp/confirm', options, body as unknown as JsonObject);
  }

  public browserMfaSetup(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/mfa/totp/setup', options, body as unknown as JsonObject);
  }

  public requestPasswordReset(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/password/forgot', options, body as unknown as JsonObject);
  }

  public resetPassword(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/password/reset', options, body as unknown as JsonObject);
  }

  public registerBrowserUser(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/auth/register', options, body as unknown as JsonObject);
  }

  public mobileRegister(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/register', options, body as unknown as JsonObject);
  }

  public mobileLogin(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/login', options, body as unknown as JsonObject);
  }

  public mobileLogout(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/logout', options);
  }

  public mobileMfaChallenge(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/mfa/challenge', options, body as unknown as JsonObject);
  }

  public mobileMfaConfirm(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/mfa/totp/confirm', options, body as unknown as JsonObject);
  }

  public mobileMfaSetup(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/mfa/totp/setup', options, body as unknown as JsonObject);
  }

  public mobileRefresh(body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', '/api/v1/mobile/auth/refresh', options, body as unknown as JsonObject);
  }

  public listUserConsents(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/consents', options);
  }

  public grantUserConsent(body: GrantUserConsentInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/consents', options, body as unknown as JsonObject);
  }

  public withdrawUserConsent(consent: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('DELETE', `/api/v1/user/consents/${encodeURIComponent(consent)}`, options);
  }

  public getCurrentUser(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/me', options);
  }

  public getUserCapabilities(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<UserCapabilities>> {
    return this.request<UserCapabilities>('GET', '/api/v1/user/capabilities', options);
  }

  public checkUserAuthorization(body: CheckUserAuthorizationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<UserAuthorizationDecision>> {
    return this.request<UserAuthorizationDecision>('POST', '/api/v1/user/authorization/check', options, body as unknown as JsonObject);
  }

  public updateUserPreferences(body: UpdateUserPreferencesInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('PUT', '/api/v1/user/preferences', options, body as unknown as JsonObject);
  }

  public listUserDevices(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/security/devices', options);
  }

  public revokeUserDevice(device: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('DELETE', `/api/v1/user/security/devices/${encodeURIComponent(device)}`, options);
  }

  public listUserSessions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/security/sessions', options);
  }

  public revokeUserSession(securitySession: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('DELETE', `/api/v1/user/security/sessions/${encodeURIComponent(securitySession)}`, options);
  }

  public getUserDashboard(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/dashboard', options);
  }

  public getUserKcaDashboard(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/kca/dashboard', options);
  }

  public listUserKcaModules(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/kca/modules', options);
  }

  public getUserKcaModule(module: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/user/kca/modules/${encodeURIComponent(module)}`, options);
  }

  public listUserKcaAssignments(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/kca/assignments', options);
  }

  public getUserKcaMentor(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/kca/mentor', options);
  }

  public listUserKcaAttendance(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/kca/attendance', options);
  }

  public listUserNotifications(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/notifications', options);
  }

  public markUserNotificationRead(notification: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', `/api/v1/user/notifications/${encodeURIComponent(notification)}/read`, options);
  }

  public listUserPaymentIntents(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/payments/intents', options);
  }

  public listUserPaymentTransactions(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/payments/transactions', options);
  }

  public getUserPaymentReceipt(receipt: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/user/payments/receipts/${encodeURIComponent(receipt)}`, options);
  }

  public createUserGivingPaymentIntent(body: CreateUserGivingPaymentIntentInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/payments/giving-intents', options, body as unknown as JsonObject);
  }

  public completeUserGivingPaymentIntent(intent: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/user/payments/giving-intents/${encodeURIComponent(intent)}/complete`, options);
  }

  public listUserPrayerRequests(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/prayers', options);
  }

  public createUserPrayerRequest(body: CreateUserPrayerRequestInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/prayers', options, body as unknown as JsonObject);
  }

  public listUserPastoralNeeds(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/needs', options);
  }

  public createUserPastoralNeed(body: CreateUserPastoralNeedInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/needs', options, body as unknown as JsonObject);
  }

  public listUserMessageConversations(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/messages/conversations', options);
  }

  public createUserMessageConversation(body: CreateUserMessageConversationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/messages/conversations', options, body as unknown as JsonObject);
  }

  public listUserConversationMessages(conversation: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/user/messages/conversations/${encodeURIComponent(conversation)}/messages`, options);
  }

  public createUserConversationMessage(conversation: string, body: CreateUserConversationMessageInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/user/messages/conversations/${encodeURIComponent(conversation)}/messages`, options, body as unknown as JsonObject);
  }

  public getUserSyncCheckpoint(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/sync/checkpoint', options);
  }

  public updateUserSyncCheckpoint(body: UpdateUserSyncCheckpointInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('PUT', '/api/v1/user/sync/checkpoint', options, body as unknown as JsonObject);
  }

  public listUserSyncChanges(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', '/api/v1/user/sync/changes', options);
  }

  public registerUserEventRegistration(event: string, body: RegisterUserEventRegistrationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/user/events/${encodeURIComponent(event)}/registrations`, options, body as unknown as JsonObject);
  }

  public recordUserEventFeedback(body: RecordUserEventFeedbackInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/events/feedback', options, body as unknown as JsonObject);
  }

  public submitUserDataSubjectRequest(body: SubmitUserDataSubjectRequestInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/privacy/data-subject-requests', options, body as unknown as JsonObject);
  }

  public storeUserFileAsset(body: StoreUserFileAssetInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/user/files', options, body as unknown as JsonObject);
  }

  public listUserFileAssets(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecordList>> {
    return this.request<ProtectedDomainRecordList>('GET', '/api/v1/user/files', options);
  }

  public streamUserFileAsset(file: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/user/files/${encodeURIComponent(file)}`, options);
  }

  public getUserEventRegistration(registration: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('GET', `/api/v1/user/events/registrations/${encodeURIComponent(registration)}`, options);
  }

  public startUserChurchMembership(church: string, body: StartUserChurchMembershipInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/user/churches/${encodeURIComponent(church)}/memberships`, options, body as unknown as JsonObject);
  }

  public listUserMemberships(options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecordList>> {
    return this.request<ProtectedDomainRecordList>('GET', '/api/v1/user/memberships', options);
  }

  public getUserHomeChurch(homeChurch: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('GET', `/api/v1/user/home-churches/${encodeURIComponent(homeChurch)}`, options);
  }

  public submitUserHomeChurchReport(homeChurch: string, body: SubmitUserHomeChurchReportInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<UserHomeChurchReportSubmission>> {
    return this.request<UserHomeChurchReportSubmission>('POST', `/api/v1/user/home-churches/${encodeURIComponent(homeChurch)}/reports`, options, body as unknown as JsonObject);
  }

  public assignAdminUserRole(user: string, body: AssignAdminUserRoleInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/users/${encodeURIComponent(user)}/role-assignments`, options, body as unknown as JsonObject);
  }

  public assignAdminRoleAssignmentScope(roleAssignment: string, body: AssignAdminRoleAssignmentScopeInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/access/role-assignments/${encodeURIComponent(roleAssignment)}/scopes`, options, body as unknown as JsonObject);
  }

  public grantAdminRolePermission(role: string, body: GrantAdminRolePermissionInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/access/roles/${encodeURIComponent(role)}/permissions`, options, body as unknown as JsonObject);
  }

  public storeAdminFileAsset(body: StoreAdminFileAssetInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/platform/files', options, body as unknown as JsonObject);
  }

  public approveAdminFileAsset(file: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/platform/files/${encodeURIComponent(file)}/approval`, options);
  }

  public streamAdminFileAssetContent(file: string, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('GET', `/api/v1/admin/platform/files/${encodeURIComponent(file)}/content`, options);
  }

  public queryAdminSearch(body: QueryAdminSearchInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminSearchResults>> {
    return this.request<AdminSearchResults>('POST', '/api/v1/admin/platform/search/queries', options, body as unknown as JsonObject);
  }

  public requestAdminAdvisory(body: RequestAdminAdvisoryInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<AdminAdvisoryResponse>> {
    return this.request<AdminAdvisoryResponse>('POST', '/api/v1/admin/platform/advisory/requests', options, body as unknown as JsonObject);
  }

  public transitionAdminKcaApplication(application: string, body: TransitionWithReasonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/applications/${encodeURIComponent(application)}/transitions`, options, body as unknown as JsonObject);
  }

  public enrollAdminKcaStudent(application: string, body: EnrollAdminKcaStudentInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/applications/${encodeURIComponent(application)}/enrollments`, options, body as unknown as JsonObject);
  }

  public transitionAdminKcaAssignment(assignment: string, body: TransitionStatusInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/assignments/${encodeURIComponent(assignment)}/transitions`, options, body as unknown as JsonObject);
  }

  public submitAdminKcaEvidence(assignment: string, body: SubmitAdminKcaEvidenceInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/assignments/${encodeURIComponent(assignment)}/evidence`, options, body as unknown as JsonObject);
  }

  public reviewAdminKcaEvidence(evidence: string, body: ReviewAdminKcaEvidenceInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/evidence/${encodeURIComponent(evidence)}/reviews`, options, body as unknown as JsonObject);
  }

  public issueAdminKcaCertificate(enrollment: string, body: IssueAdminKcaCertificateInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/enrollments/${encodeURIComponent(enrollment)}/certificates`, options, body as unknown as JsonObject);
  }

  public createAdminKcaYear(body: CreateAdminKcaYearInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/kca/years', options, body as unknown as JsonObject);
  }

  public createAdminKcaCohort(year: string, body: CreateAdminKcaCohortInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/years/${encodeURIComponent(year)}/cohorts`, options, body as unknown as JsonObject);
  }

  public createAdminKcaModule(body: CreateAdminKcaModuleInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/kca/modules', options, body as unknown as JsonObject);
  }

  public createAdminKcaLesson(module: string, body: CreateAdminKcaLessonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/modules/${encodeURIComponent(module)}/lessons`, options, body as unknown as JsonObject);
  }

  public recordAdminKcaAttendance(enrollment: string, body: RecordAdminKcaAttendanceInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/kca/enrollments/${encodeURIComponent(enrollment)}/attendance`, options, body as unknown as JsonObject);
  }

  public createAdminPressPublication(body: CreateAdminPressPublicationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/press/publications', options, body as unknown as JsonObject);
  }

  public transitionAdminPressPublication(publication: string, body: TransitionWithReasonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/press/publications/${encodeURIComponent(publication)}/transitions`, options, body as unknown as JsonObject);
  }

  public assignAdminPressPublicationIsbn(publication: string, body: AssignAdminPressPublicationIsbnInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/press/publications/${encodeURIComponent(publication)}/isbn`, options, body as unknown as JsonObject);
  }

  public addAdminPressPublicationContributor(publication: string, body: AddAdminPressPublicationContributorInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/press/publications/${encodeURIComponent(publication)}/contributors`, options, body as unknown as JsonObject);
  }

  public createAdminPressTranslation(publication: string, body: CreateAdminPressTranslationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/press/publications/${encodeURIComponent(publication)}/translations`, options, body as unknown as JsonObject);
  }

  public transitionAdminPressTranslation(translation: string, body: TransitionWithReasonInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/press/translations/${encodeURIComponent(translation)}/transitions`, options, body as unknown as JsonObject);
  }

  public createAdminMinistryEvent(body: CreateAdminMinistryEventInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/events', options, body as unknown as JsonObject);
  }

  public registerAdminEventRegistration(event: string, body: RegisterAdminEventRegistrationInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/events/${encodeURIComponent(event)}/registrations`, options, body as unknown as JsonObject);
  }

  public recordAdminEventAttendance(registration: string, body: RecordAdminEventAttendanceInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/events/registrations/${encodeURIComponent(registration)}/attendance`, options, body as unknown as JsonObject);
  }

  public recordAdminEventFeedback(registration: string, body: RecordAdminEventFeedbackInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/events/registrations/${encodeURIComponent(registration)}/feedback`, options, body as unknown as JsonObject);
  }

  public createAdminPaymentIntent(body: CreateAdminPaymentIntentInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/finance/payment-intents', options, body as unknown as JsonObject);
  }

  public requestAdminPaymentRefund(transaction: string, body: RequestAdminPaymentRefundInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/finance/payment-transactions/${encodeURIComponent(transaction)}/refunds`, options, body as unknown as JsonObject);
  }

  public createAdminCommunicationTemplate(body: CreateAdminCommunicationTemplateInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/communications/templates', options, body as unknown as JsonObject);
  }

  public createAdminCommunicationAudience(body: CreateAdminCommunicationAudienceInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/communications/audiences', options, body as unknown as JsonObject);
  }

  public prepareAdminCommunicationBroadcast(body: PrepareAdminCommunicationBroadcastInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/communications/broadcasts', options, body as unknown as JsonObject);
  }

  public resolveAdminCommunicationBroadcast(broadcast: string, body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<JsonValue>> {
    return this.request<JsonValue>('POST', `/api/v1/admin/communications/broadcasts/${encodeURIComponent(broadcast)}/resolve`, options, body as unknown as JsonObject);
  }

  public attemptAdminCommunicationDelivery(recipient: string, body: IdempotencyKeyInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/communications/recipients/${encodeURIComponent(recipient)}/deliveries`, options, body as unknown as JsonObject);
  }

  public createAdminCommunicationNotification(recipient: string, body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/communications/recipients/${encodeURIComponent(recipient)}/notifications`, options, body as unknown as JsonObject);
  }

  public createAdminAlertRule(body: CreateAdminAlertRuleInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/reporting/alert-rules', options, body as unknown as JsonObject);
  }

  public setAdminAlertRuleEnabled(alertRule: string, body: SetAdminAlertRuleEnabledInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/reporting/alert-rules/${encodeURIComponent(alertRule)}/enabled`, options, body as unknown as JsonObject);
  }

  public evaluateAdminAlertRule(alertRule: string, body: EvaluateAdminAlertRuleInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/reporting/alert-rules/${encodeURIComponent(alertRule)}/evaluations`, options, body as unknown as JsonObject);
  }

  public acknowledgeAdminAlertOccurrence(occurrence: string, body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/reporting/alert-occurrences/${encodeURIComponent(occurrence)}/acknowledgement`, options, body as unknown as JsonObject);
  }

  public resolveAdminAlertOccurrence(occurrence: string, body: ReasonCodeInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/reporting/alert-occurrences/${encodeURIComponent(occurrence)}/resolution`, options, body as unknown as JsonObject);
  }

  public submitAdminDataSubjectRequest(body: SubmitAdminDataSubjectRequestInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/privacy/data-subject-requests', options, body as unknown as JsonObject);
  }

  public beginAdminDataExport(dataSubjectRequest: string, body: BeginAdminDataExportInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/privacy/data-subject-requests/${encodeURIComponent(dataSubjectRequest)}/exports/begin`, options, body as unknown as JsonObject);
  }

  public completeAdminDataExport(dataSubjectRequest: string, body: CompleteAdminDataExportInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/privacy/data-subject-requests/${encodeURIComponent(dataSubjectRequest)}/exports/complete`, options, body as unknown as JsonObject);
  }

  public expireAdminDataExport(dataSubjectRequest: string, body: JsonObject = {}, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', `/api/v1/admin/privacy/data-subject-requests/${encodeURIComponent(dataSubjectRequest)}/exports/expire`, options, body as unknown as JsonObject);
  }

  public reportAdminSafeguardingIncident(body: ReportAdminSafeguardingIncidentInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/safeguarding/incidents', options, body as unknown as JsonObject);
  }

  public registerAdminGuardianRelationship(body: RegisterAdminGuardianRelationshipInput, options: ProtectedRequestOptions = {}): Promise<SuccessEnvelope<ProtectedDomainRecord>> {
    return this.request<ProtectedDomainRecord>('POST', '/api/v1/admin/safeguarding/guardian-relationships', options, body as unknown as JsonObject);
  }

  private async request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    options: ProtectedRequestOptions,
    body?: JsonObject,
  ): Promise<SuccessEnvelope<T>> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    const url = new URL(path, this.baseUrl);
    for (const [key, value] of Object.entries(options.query ?? {})) {
      if (value !== undefined) url.searchParams.append(key, String(value));
    }
    if (options.correlationId) headers['X-Correlation-ID'] = options.correlationId;
    if (options.csrfToken) {
      headers['X-CSRF-TOKEN'] = options.csrfToken;
      headers['X-XSRF-TOKEN'] = options.csrfToken;
    }
    if (options.bearerToken) headers.Authorization = 'Bearer ' + options.bearerToken;
    if (options.deviceIdentifier) headers['X-Device-Identifier'] = options.deviceIdentifier;
    if (options.scope) {
      headers['X-Scope-Type'] = options.scope.type;
      headers['X-Scope-ID'] = options.scope.id;
    }
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    const response = await this.fetcher(url, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: options.signal,
    });
    const payload = (await response.json()) as SuccessEnvelope<JsonValue> | ErrorEnvelope;
    if (!response.ok) throw new ProtectedApiError(response.status, payload as ErrorEnvelope);
    return payload as SuccessEnvelope<T>;
  }
}