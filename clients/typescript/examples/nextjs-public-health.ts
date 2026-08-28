import {
  FamilyHousePublicApiClient,
  PublicApiError,
  type CorrelationId,
  type SuccessEnvelope,
  type ApiStatusData,
  type HealthData,
  type ReadinessData,
} from '../src/public-api.js';

export interface NextPublicApiEnvironment {
  NEXT_PUBLIC_FHC_API_BASE_URL?: string;
}

export interface PublicHealthSnapshot {
  api: SuccessEnvelope<ApiStatusData>;
  health: SuccessEnvelope<HealthData>;
  readiness: SuccessEnvelope<ReadinessData>;
}

export interface PublicApiFailureView {
  status: number;
  code: string;
  message: string;
  correlationId: CorrelationId;
}

export function createNextPublicApiClient(
  environment: NextPublicApiEnvironment,
  fetcher: typeof fetch = globalThis.fetch,
): FamilyHousePublicApiClient {
  const configuredBaseUrl = environment.NEXT_PUBLIC_FHC_API_BASE_URL;

  if (configuredBaseUrl === undefined || configuredBaseUrl.trim() === '') {
    throw new Error('NEXT_PUBLIC_FHC_API_BASE_URL is required.');
  }

  const baseUrl = new URL(configuredBaseUrl);

  if (baseUrl.protocol !== 'https:' && baseUrl.protocol !== 'http:') {
    throw new Error('NEXT_PUBLIC_FHC_API_BASE_URL must be an absolute HTTP(S) URL.');
  }

  return new FamilyHousePublicApiClient(baseUrl.toString(), fetcher);
}

export async function loadPublicHealthSnapshot(
  client: FamilyHousePublicApiClient,
  correlationId: CorrelationId = globalThis.crypto.randomUUID(),
): Promise<PublicHealthSnapshot> {
  const options = { correlationId };
  const [api, health, readiness] = await Promise.all([
    client.getApiStatus(options),
    client.getHealth(options),
    client.getReadiness(options),
  ]);

  return { api, health, readiness };
}

export function toPublicApiFailure(error: unknown): PublicApiFailureView | null {
  if (!(error instanceof PublicApiError)) {
    return null;
  }

  return {
    status: error.status,
    code: error.envelope.error.code,
    message: error.envelope.error.message,
    correlationId: error.envelope.correlation_id,
  };
}
