import '../lib/public_api.dart';

final class FlutterPublicApiEnvironment {
  const FlutterPublicApiEnvironment._(this.baseUri);

  factory FlutterPublicApiEnvironment.fromDartDefines() {
    const configuredBaseUrl = String.fromEnvironment('FHC_PUBLIC_API_BASE_URL');

    if (configuredBaseUrl.isEmpty) {
      throw const FormatException(
        'Pass --dart-define=FHC_PUBLIC_API_BASE_URL=https://api.example.org',
      );
    }

    final baseUri = Uri.parse(configuredBaseUrl);

    if (!baseUri.isAbsolute || (baseUri.scheme != 'https' && baseUri.scheme != 'http')) {
      throw const FormatException('FHC_PUBLIC_API_BASE_URL must be an absolute HTTP(S) URL.');
    }

    return FlutterPublicApiEnvironment._(baseUri);
  }

  final Uri baseUri;
}

final class FlutterPublicHealthSnapshot {
  const FlutterPublicHealthSnapshot({required this.api, required this.health, required this.readiness});

  final SuccessEnvelope<ApiStatusData> api;
  final SuccessEnvelope<HealthData> health;
  final SuccessEnvelope<ReadinessData> readiness;
}

FamilyHousePublicApiClient createFlutterPublicApiClient({
  required PublicApiTransport transport,
  FlutterPublicApiEnvironment? environment,
}) {
  final resolvedEnvironment = environment ?? FlutterPublicApiEnvironment.fromDartDefines();

  return FamilyHousePublicApiClient(baseUri: resolvedEnvironment.baseUri, transport: transport);
}

Future<FlutterPublicHealthSnapshot> loadFlutterPublicHealthSnapshot(
  FamilyHousePublicApiClient client, {
  String? correlationId,
}) async {
  final responses = await Future.wait<Object>(<Future<Object>>[
    client.getApiStatus(correlationId: correlationId),
    client.getHealth(correlationId: correlationId),
    client.getReadiness(correlationId: correlationId),
  ]);

  return FlutterPublicHealthSnapshot(
    api: responses[0] as SuccessEnvelope<ApiStatusData>,
    health: responses[1] as SuccessEnvelope<HealthData>,
    readiness: responses[2] as SuccessEnvelope<ReadinessData>,
  );
}

String describePublicApiFailure(PublicApiException exception) =>
    '${exception.code} [correlation_id=${exception.correlationId}]';
