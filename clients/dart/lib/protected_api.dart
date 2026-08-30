// Generated from openapi/protected-v1.openapi.json (SHA-256: ac99484ebb77fdc19e1ff81bbc49914584022c220576d56f1cc518487437318d).
// Do not edit directly. Run: php scripts/generate-protected-api.php

typedef JsonMap = Map<String, Object?>;

abstract interface class ProtectedApiTransport {
  Future<ProtectedApiTransportResponse> send(
    String method,
    Uri uri, {
    Map<String, String> headers = const {},
    JsonMap? body,
    bool includeCredentials = true,
  });
}

final class ProtectedApiTransportResponse {
  const ProtectedApiTransportResponse({required this.statusCode, required this.body});
  final int statusCode;
  final JsonMap body;
}

final class ProtectedRequestOptions {
  const ProtectedRequestOptions({
    this.correlationId,
    this.query = const {},
    this.csrfToken,
    this.bearerToken,
    this.deviceIdentifier,
    this.scopeType,
    this.scopeId,
  });
  final String? correlationId;
  final JsonMap query;
  final String? csrfToken;
  final String? bearerToken;
  final String? deviceIdentifier;
  final String? scopeType;
  final String? scopeId;
}

final class ProtectedApiException implements Exception {
  const ProtectedApiException(this.statusCode, this.payload);
  final int statusCode;
  final JsonMap payload;
}

final class FamilyHouseProtectedApiClient {
  const FamilyHouseProtectedApiClient({required this.baseUri, required this.transport});
  final Uri baseUri;
  final ProtectedApiTransport transport;

  Future<JsonMap> listAdminPermissions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/access/permissions', options);

  Future<JsonMap> listAdminRoles({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/access/roles', options);

  Future<JsonMap> listAdminScopeAssignments({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/access/scope-assignments', options);

  Future<JsonMap> listAdminAuditEvents({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/security/audit-events', options);

  Future<JsonMap> listAdminAccessDecisions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/security/access-decisions', options);

  Future<JsonMap> listAdminCountries({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/organization/countries', options);

  Future<JsonMap> createAdminCountry({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/organization/countries', options, body: body);

  Future<JsonMap> listAdminAdministrativeLevels({required String country, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/organization/countries/${Uri.encodeComponent(country)}/levels', options);

  Future<JsonMap> createAdminAdministrativeLevel({required String country, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/organization/countries/${Uri.encodeComponent(country)}/levels', options, body: body);

  Future<JsonMap> listAdminLocations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/organization/locations', options);

  Future<JsonMap> createAdminLocation({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/organization/locations', options, body: body);

  Future<JsonMap> listAdminAdministrativeUnits({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/organization/units', options);

  Future<JsonMap> createAdminAdministrativeUnit({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/organization/units', options, body: body);

  Future<JsonMap> moveAdminAdministrativeUnit({required String unit, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PATCH', '/api/v1/admin/organization/units/${Uri.encodeComponent(unit)}/parent', options, body: body);

  Future<JsonMap> listAdminPlatformConfigurations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/platform/configurations', options);

  Future<JsonMap> upsertAdminPlatformConfiguration({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/admin/platform/configurations', options, body: body);

  Future<JsonMap> listAdminFeatureFlags({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/platform/feature-flags', options);

  Future<JsonMap> upsertAdminFeatureFlag({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/admin/platform/feature-flags', options, body: body);

  Future<JsonMap> enableAdminFeatureFlag({required String featureFlag, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/feature-flags/${Uri.encodeComponent(featureFlag)}/enabled', options);

  Future<JsonMap> disableAdminFeatureFlag({required String featureFlag, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/admin/platform/feature-flags/${Uri.encodeComponent(featureFlag)}/enabled', options);

  Future<JsonMap> getAdminObjectStorage({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/platform/storage/object-storage', options);

  Future<JsonMap> configureAdminObjectStorage({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/admin/platform/storage/object-storage', options, body: body);

  Future<JsonMap> activateAdminObjectStorage({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/storage/object-storage/activation', options);

  Future<JsonMap> deactivateAdminObjectStorage({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/admin/platform/storage/object-storage/activation', options);

  Future<JsonMap> validateAdminObjectStorage({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/storage/object-storage/validation', options);

  Future<JsonMap> getAdminMapsProvider({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/platform/maps', options);

  Future<JsonMap> configureAdminMapsProvider({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/admin/platform/maps', options, body: body);

  Future<JsonMap> activateAdminMapsProvider({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/maps/activation', options, body: body);

  Future<JsonMap> deactivateAdminMapsProvider({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/admin/platform/maps/activation', options);

  Future<JsonMap> listAdminContentPages({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/content/pages', options);

  Future<JsonMap> createAdminContentPage({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/content/pages', options, body: body);

  Future<JsonMap> updateAdminContentPage({required String page, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/admin/content/pages/${Uri.encodeComponent(page)}', options, body: body);

  Future<JsonMap> createAdminContentPageItem({required String page, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/content/pages/${Uri.encodeComponent(page)}/items', options, body: body);

  Future<JsonMap> listAdminChurches({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/church/churches', options);

  Future<JsonMap> createAdminChurch({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/churches', options, body: body);

  Future<JsonMap> listAdminHomeChurches({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/church/home-churches', options);

  Future<JsonMap> listAdminHomeChurchApplications({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/church/home-church-applications', options);

  Future<JsonMap> createAdminHomeChurchApplication({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/home-church-applications', options, body: body);

  Future<JsonMap> transitionAdminHomeChurchApplication({required String application, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/home-church-applications/${Uri.encodeComponent(application)}/transitions', options, body: body);

  Future<JsonMap> listAdminFirstTimers({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/church/first-timers', options);

  Future<JsonMap> createAdminFirstTimer({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/first-timers', options, body: body);

  Future<JsonMap> listAdminChurchFollowUpTasks({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/church/follow-up-tasks', options);

  Future<JsonMap> completeAdminChurchFollowUpTask({required String task, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/follow-up-tasks/${Uri.encodeComponent(task)}/completion', options, body: body);

  Future<JsonMap> startAdminChurchMembership({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/memberships', options, body: body);

  Future<JsonMap> endAdminChurchMembership({required String membership, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/church/memberships/${Uri.encodeComponent(membership)}/end', options, body: body);

  Future<JsonMap> listAdminMissionCrusades({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/mission/crusades', options);

  Future<JsonMap> listAdminMissionSouls({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/mission/souls', options);

  Future<JsonMap> captureAdminMissionSoul({required String crusade, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/crusades/${Uri.encodeComponent(crusade)}/souls', options, body: body);

  Future<JsonMap> assignAdminMissionSoulMentor({required String soul, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/souls/${Uri.encodeComponent(soul)}/mentor-assignment', options, body: body);

  Future<JsonMap> recordAdminMissionSoulFollowUp({required String soul, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/souls/${Uri.encodeComponent(soul)}/follow-ups', options, body: body);

  Future<JsonMap> completeAdminMissionSoulFollowUp({required String soul, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/souls/${Uri.encodeComponent(soul)}/follow-up-completion', options, body: body);

  Future<JsonMap> transitionAdminMissionInvitation({required String invitation, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/invitations/${Uri.encodeComponent(invitation)}/transitions', options, body: body);

  Future<JsonMap> listAdminMissionInvitations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/mission/invitations', options);

  Future<JsonMap> createAdminMissionInvitation({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/mission/invitations', options, body: body);

  Future<JsonMap> listAdminCatalogKcaApplications({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/kca/applications', options);

  Future<JsonMap> listAdminCatalogKcaEnrollments({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/kca/enrollments', options);

  Future<JsonMap> listAdminCatalogKcaEvidenceSubmissions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/kca/evidence-submissions', options);

  Future<JsonMap> listAdminCatalogKcaAssessmentResults({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/kca/assessment-results', options);

  Future<JsonMap> listAdminCatalogKcaCertificates({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/kca/certificates', options);

  Future<JsonMap> listAdminCatalogPressPublications({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/press/publications', options);

  Future<JsonMap> listAdminCatalogPressTranslations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/press/translations', options);

  Future<JsonMap> listAdminCatalogEvents({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/events/events', options);

  Future<JsonMap> listAdminCatalogEventRegistrations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/events/registrations', options);

  Future<JsonMap> listAdminCatalogPaymentIntents({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-intents', options);

  Future<JsonMap> listAdminCatalogPaymentTransactions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-transactions', options);

  Future<JsonMap> listAdminCatalogPaymentReconciliations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-reconciliations', options);

  Future<JsonMap> listAdminCatalogPaymentReceipts({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-receipts', options);

  Future<JsonMap> listAdminCatalogPaymentRefunds({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-refunds', options);

  Future<JsonMap> listAdminCatalogPaymentDisputes({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/finance/payment-disputes', options);

  Future<JsonMap> listAdminCatalogCommunicationTemplates({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/communications/templates', options);

  Future<JsonMap> listAdminCatalogCommunicationAudiences({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/communications/audiences', options);

  Future<JsonMap> listAdminCatalogCommunicationBroadcasts({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/communications/broadcasts', options);

  Future<JsonMap> listAdminCatalogCommunicationDeliveries({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/communications/delivery-attempts', options);

  Future<JsonMap> listAdminCatalogCommunicationNotifications({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/communications/notifications', options);

  Future<JsonMap> listAdminCatalogAlertRules({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/reporting/alert-rules', options);

  Future<JsonMap> listAdminCatalogAlertOccurrences({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/reporting/alert-occurrences', options);

  Future<JsonMap> listAdminCatalogDataSubjectRequests({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/privacy/data-subject-requests', options);

  Future<JsonMap> listAdminCatalogFileAssets({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/catalog/platform/files', options);

  Future<JsonMap> listAdminUsers({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/users', options);

  Future<JsonMap> getAdminUser({required String user, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/users/${Uri.encodeComponent(user)}', options);

  Future<JsonMap> suspendAdminUser({required String user, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/users/${Uri.encodeComponent(user)}/suspension', options, body: body);

  Future<JsonMap> reactivateAdminUser({required String user, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/admin/users/${Uri.encodeComponent(user)}/suspension', options);

  Future<JsonMap> getBrowserCsrfCookie({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/auth/csrf-cookie', options);

  Future<JsonMap> sendEmailVerification({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/email/verification-notification', options);

  Future<JsonMap> verifyEmail({required String id, required String hash, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/auth/email/verify/${Uri.encodeComponent(id)}/${Uri.encodeComponent(hash)}', options);

  Future<JsonMap> browserLogin({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/login', options, body: body);

  Future<JsonMap> browserLogout({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/logout', options);

  Future<JsonMap> browserMfaChallenge({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/mfa/challenge', options, body: body);

  Future<JsonMap> browserMfaConfirm({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/mfa/totp/confirm', options, body: body);

  Future<JsonMap> browserMfaSetup({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/mfa/totp/setup', options, body: body);

  Future<JsonMap> requestPasswordReset({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/password/forgot', options, body: body);

  Future<JsonMap> resetPassword({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/password/reset', options, body: body);

  Future<JsonMap> registerBrowserUser({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/auth/register', options, body: body);

  Future<JsonMap> mobileRegister({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/register', options, body: body);

  Future<JsonMap> mobileLogin({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/login', options, body: body);

  Future<JsonMap> mobileLogout({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/logout', options);

  Future<JsonMap> mobileMfaChallenge({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/mfa/challenge', options, body: body);

  Future<JsonMap> mobileMfaConfirm({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/mfa/totp/confirm', options, body: body);

  Future<JsonMap> mobileMfaSetup({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/mfa/totp/setup', options, body: body);

  Future<JsonMap> mobileRefresh({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/mobile/auth/refresh', options, body: body);

  Future<JsonMap> listUserConsents({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/consents', options);

  Future<JsonMap> grantUserConsent({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/consents', options, body: body);

  Future<JsonMap> withdrawUserConsent({required String consent, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/user/consents/${Uri.encodeComponent(consent)}', options);

  Future<JsonMap> getCurrentUser({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/me', options);

  Future<JsonMap> getUserCapabilities({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/capabilities', options);

  Future<JsonMap> checkUserAuthorization({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/authorization/check', options, body: body);

  Future<JsonMap> updateUserPreferences({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/user/preferences', options, body: body);

  Future<JsonMap> listUserDevices({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/security/devices', options);

  Future<JsonMap> revokeUserDevice({required String device, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/user/security/devices/${Uri.encodeComponent(device)}', options);

  Future<JsonMap> listUserSessions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/security/sessions', options);

  Future<JsonMap> revokeUserSession({required String securitySession, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('DELETE', '/api/v1/user/security/sessions/${Uri.encodeComponent(securitySession)}', options);

  Future<JsonMap> getUserDashboard({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/dashboard', options);

  Future<JsonMap> getUserKcaDashboard({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/dashboard', options);

  Future<JsonMap> listUserKcaModules({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/modules', options);

  Future<JsonMap> getUserKcaModule({required String module, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/modules/${Uri.encodeComponent(module)}', options);

  Future<JsonMap> listUserKcaAssignments({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/assignments', options);

  Future<JsonMap> getUserKcaMentor({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/mentor', options);

  Future<JsonMap> listUserKcaAttendance({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/kca/attendance', options);

  Future<JsonMap> listUserNotifications({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/notifications', options);

  Future<JsonMap> markUserNotificationRead({required String notification, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/notifications/${Uri.encodeComponent(notification)}/read', options);

  Future<JsonMap> listUserPaymentIntents({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/payments/intents', options);

  Future<JsonMap> listUserPaymentTransactions({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/payments/transactions', options);

  Future<JsonMap> getUserPaymentReceipt({required String receipt, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/payments/receipts/${Uri.encodeComponent(receipt)}', options);

  Future<JsonMap> createUserGivingPaymentIntent({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/payments/giving-intents', options, body: body);

  Future<JsonMap> completeUserGivingPaymentIntent({required String intent, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/payments/giving-intents/${Uri.encodeComponent(intent)}/complete', options);

  Future<JsonMap> listUserPrayerRequests({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/prayers', options);

  Future<JsonMap> createUserPrayerRequest({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/prayers', options, body: body);

  Future<JsonMap> listUserPastoralNeeds({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/needs', options);

  Future<JsonMap> createUserPastoralNeed({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/needs', options, body: body);

  Future<JsonMap> listUserMessageConversations({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/messages/conversations', options);

  Future<JsonMap> createUserMessageConversation({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/messages/conversations', options, body: body);

  Future<JsonMap> listUserConversationMessages({required String conversation, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/messages/conversations/${Uri.encodeComponent(conversation)}/messages', options);

  Future<JsonMap> createUserConversationMessage({required String conversation, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/messages/conversations/${Uri.encodeComponent(conversation)}/messages', options, body: body);

  Future<JsonMap> getUserSyncCheckpoint({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/sync/checkpoint', options);

  Future<JsonMap> updateUserSyncCheckpoint({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('PUT', '/api/v1/user/sync/checkpoint', options, body: body);

  Future<JsonMap> listUserSyncChanges({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/sync/changes', options);

  Future<JsonMap> registerUserEventRegistration({required String event, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/events/${Uri.encodeComponent(event)}/registrations', options, body: body);

  Future<JsonMap> recordUserEventFeedback({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/events/feedback', options, body: body);

  Future<JsonMap> submitUserDataSubjectRequest({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/privacy/data-subject-requests', options, body: body);

  Future<JsonMap> storeUserFileAsset({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/files', options, body: body);

  Future<JsonMap> listUserFileAssets({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/files', options);

  Future<JsonMap> streamUserFileAsset({required String file, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/files/${Uri.encodeComponent(file)}', options);

  Future<JsonMap> getUserEventRegistration({required String registration, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/events/registrations/${Uri.encodeComponent(registration)}', options);

  Future<JsonMap> startUserChurchMembership({required String church, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/churches/${Uri.encodeComponent(church)}/memberships', options, body: body);

  Future<JsonMap> listUserMemberships({ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/memberships', options);

  Future<JsonMap> getUserHomeChurch({required String homeChurch, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/user/home-churches/${Uri.encodeComponent(homeChurch)}', options);

  Future<JsonMap> submitUserHomeChurchReport({required String homeChurch, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/user/home-churches/${Uri.encodeComponent(homeChurch)}/reports', options, body: body);

  Future<JsonMap> assignAdminUserRole({required String user, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/users/${Uri.encodeComponent(user)}/role-assignments', options, body: body);

  Future<JsonMap> assignAdminRoleAssignmentScope({required String roleAssignment, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/access/role-assignments/${Uri.encodeComponent(roleAssignment)}/scopes', options, body: body);

  Future<JsonMap> grantAdminRolePermission({required String role, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/access/roles/${Uri.encodeComponent(role)}/permissions', options, body: body);

  Future<JsonMap> storeAdminFileAsset({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/files', options, body: body);

  Future<JsonMap> approveAdminFileAsset({required String file, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/files/${Uri.encodeComponent(file)}/approval', options);

  Future<JsonMap> streamAdminFileAssetContent({required String file, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('GET', '/api/v1/admin/platform/files/${Uri.encodeComponent(file)}/content', options);

  Future<JsonMap> queryAdminSearch({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/search/queries', options, body: body);

  Future<JsonMap> requestAdminAdvisory({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/platform/advisory/requests', options, body: body);

  Future<JsonMap> transitionAdminKcaApplication({required String application, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/applications/${Uri.encodeComponent(application)}/transitions', options, body: body);

  Future<JsonMap> enrollAdminKcaStudent({required String application, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/applications/${Uri.encodeComponent(application)}/enrollments', options, body: body);

  Future<JsonMap> transitionAdminKcaAssignment({required String assignment, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/assignments/${Uri.encodeComponent(assignment)}/transitions', options, body: body);

  Future<JsonMap> submitAdminKcaEvidence({required String assignment, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/assignments/${Uri.encodeComponent(assignment)}/evidence', options, body: body);

  Future<JsonMap> reviewAdminKcaEvidence({required String evidence, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/evidence/${Uri.encodeComponent(evidence)}/reviews', options, body: body);

  Future<JsonMap> issueAdminKcaCertificate({required String enrollment, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/enrollments/${Uri.encodeComponent(enrollment)}/certificates', options, body: body);

  Future<JsonMap> createAdminKcaYear({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/years', options, body: body);

  Future<JsonMap> createAdminKcaCohort({required String year, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/years/${Uri.encodeComponent(year)}/cohorts', options, body: body);

  Future<JsonMap> createAdminKcaModule({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/modules', options, body: body);

  Future<JsonMap> createAdminKcaLesson({required String module, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/modules/${Uri.encodeComponent(module)}/lessons', options, body: body);

  Future<JsonMap> recordAdminKcaAttendance({required String enrollment, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/kca/enrollments/${Uri.encodeComponent(enrollment)}/attendance', options, body: body);

  Future<JsonMap> createAdminPressPublication({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/publications', options, body: body);

  Future<JsonMap> transitionAdminPressPublication({required String publication, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/publications/${Uri.encodeComponent(publication)}/transitions', options, body: body);

  Future<JsonMap> assignAdminPressPublicationIsbn({required String publication, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/publications/${Uri.encodeComponent(publication)}/isbn', options, body: body);

  Future<JsonMap> addAdminPressPublicationContributor({required String publication, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/publications/${Uri.encodeComponent(publication)}/contributors', options, body: body);

  Future<JsonMap> createAdminPressTranslation({required String publication, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/publications/${Uri.encodeComponent(publication)}/translations', options, body: body);

  Future<JsonMap> transitionAdminPressTranslation({required String translation, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/press/translations/${Uri.encodeComponent(translation)}/transitions', options, body: body);

  Future<JsonMap> createAdminMinistryEvent({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/events', options, body: body);

  Future<JsonMap> registerAdminEventRegistration({required String event, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/events/${Uri.encodeComponent(event)}/registrations', options, body: body);

  Future<JsonMap> recordAdminEventAttendance({required String registration, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/events/registrations/${Uri.encodeComponent(registration)}/attendance', options, body: body);

  Future<JsonMap> recordAdminEventFeedback({required String registration, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/events/registrations/${Uri.encodeComponent(registration)}/feedback', options, body: body);

  Future<JsonMap> createAdminPaymentIntent({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/finance/payment-intents', options, body: body);

  Future<JsonMap> requestAdminPaymentRefund({required String transaction, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/finance/payment-transactions/${Uri.encodeComponent(transaction)}/refunds', options, body: body);

  Future<JsonMap> createAdminCommunicationTemplate({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/templates', options, body: body);

  Future<JsonMap> createAdminCommunicationAudience({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/audiences', options, body: body);

  Future<JsonMap> prepareAdminCommunicationBroadcast({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/broadcasts', options, body: body);

  Future<JsonMap> resolveAdminCommunicationBroadcast({required String broadcast, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/broadcasts/${Uri.encodeComponent(broadcast)}/resolve', options, body: body);

  Future<JsonMap> attemptAdminCommunicationDelivery({required String recipient, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/recipients/${Uri.encodeComponent(recipient)}/deliveries', options, body: body);

  Future<JsonMap> createAdminCommunicationNotification({required String recipient, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/communications/recipients/${Uri.encodeComponent(recipient)}/notifications', options, body: body);

  Future<JsonMap> createAdminAlertRule({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/reporting/alert-rules', options, body: body);

  Future<JsonMap> setAdminAlertRuleEnabled({required String alertRule, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/reporting/alert-rules/${Uri.encodeComponent(alertRule)}/enabled', options, body: body);

  Future<JsonMap> evaluateAdminAlertRule({required String alertRule, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/reporting/alert-rules/${Uri.encodeComponent(alertRule)}/evaluations', options, body: body);

  Future<JsonMap> acknowledgeAdminAlertOccurrence({required String occurrence, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/reporting/alert-occurrences/${Uri.encodeComponent(occurrence)}/acknowledgement', options, body: body);

  Future<JsonMap> resolveAdminAlertOccurrence({required String occurrence, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/reporting/alert-occurrences/${Uri.encodeComponent(occurrence)}/resolution', options, body: body);

  Future<JsonMap> submitAdminDataSubjectRequest({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/privacy/data-subject-requests', options, body: body);

  Future<JsonMap> beginAdminDataExport({required String dataSubjectRequest, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/privacy/data-subject-requests/${Uri.encodeComponent(dataSubjectRequest)}/exports/begin', options, body: body);

  Future<JsonMap> completeAdminDataExport({required String dataSubjectRequest, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/privacy/data-subject-requests/${Uri.encodeComponent(dataSubjectRequest)}/exports/complete', options, body: body);

  Future<JsonMap> expireAdminDataExport({required String dataSubjectRequest, JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/privacy/data-subject-requests/${Uri.encodeComponent(dataSubjectRequest)}/exports/expire', options, body: body);

  Future<JsonMap> reportAdminSafeguardingIncident({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/safeguarding/incidents', options, body: body);

  Future<JsonMap> registerAdminGuardianRelationship({JsonMap body = const {}, ProtectedRequestOptions options = const ProtectedRequestOptions()}) =>
      _request('POST', '/api/v1/admin/safeguarding/guardian-relationships', options, body: body);

  Future<JsonMap> _request(
    String method,
    String path,
    ProtectedRequestOptions options, {
    JsonMap? body,
  }) async {
    var uri = baseUri.resolve(path);
    if (options.query.isNotEmpty) {
      uri = uri.replace(queryParameters: options.query.map((key, value) => MapEntry(key, '$value')));
    }
    final headers = <String, String>{'Accept': 'application/json'};
    if (options.correlationId != null) headers['X-Correlation-ID'] = options.correlationId!;
    if (options.csrfToken != null) headers['X-XSRF-TOKEN'] = options.csrfToken!;
    if (options.bearerToken != null) headers['Authorization'] = 'Bearer ' + options.bearerToken!;
    if (options.deviceIdentifier != null) headers['X-Device-Identifier'] = options.deviceIdentifier!;
    if (options.scopeType != null && options.scopeId != null) {
      headers['X-Scope-Type'] = options.scopeType!;
      headers['X-Scope-ID'] = options.scopeId!;
    }
    final response = await transport.send(method, uri, headers: headers, body: body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ProtectedApiException(response.statusCode, response.body);
    }
    return response.body;
  }
}