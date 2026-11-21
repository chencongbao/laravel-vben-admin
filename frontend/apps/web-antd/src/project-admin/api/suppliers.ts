import { requestClient } from '#/api/request';

export type RunMode = 'production' | 'shadow' | 'testing';
export type SourceStatus = 'active' | 'degraded' | 'disabled' | 'draft' | 'suspended';
export type HealthStatus = 'degraded' | 'delayed' | 'healthy' | 'recovering' | 'unavailable' | 'unknown';
export type CredentialStorage = 'encrypted' | 'reference';
export type CapabilityStatus =
  | 'competition_limited'
  | 'not_supported'
  | 'package_limited'
  | 'partial'
  | 'supported'
  | 'unknown';

export interface Source {
  adapter: string;
  api_base_url: string;
  api_version?: null | string;
  code: string;
  concurrency_limit: number;
  created_at: string;
  credential_storage: CredentialStorage;
  credentials_configured: boolean;
  health_status: HealthStatus;
  id: number;
  last_failure_at?: null | string;
  last_success_at?: null | string;
  name: string;
  notes?: null | string;
  request_timeout: number;
  rate_limit_per_day?: null | number;
  rate_limit_per_hour?: null | number;
  rate_limit_per_minute?: null | number;
  retry_backoff_ms: number;
  retry_max_attempts: number;
  retry_max_delay_ms: number;
  run_mode: RunMode;
  sport_code: string;
  score_poll_claimed_until?: null | string;
  score_poll_enabled: boolean;
  score_poll_interval_seconds: number;
  score_poll_last_run_id?: null | number;
  score_poll_next_at?: null | string;
  status: SourceStatus;
  updated_at: string;
  website?: null | string;
}

export interface SourcePayload {
  adapter: string;
  api_base_url: string;
  api_version?: string;
  code: string;
  concurrency_limit: number;
  credential_reference?: string;
  credential_storage: CredentialStorage;
  credentials?: Record<string, string>;
  name: string;
  notes?: string;
  request_timeout: number;
  rate_limit_per_day?: null | number;
  rate_limit_per_hour?: null | number;
  rate_limit_per_minute?: null | number;
  retry_backoff_ms: number;
  retry_max_attempts: number;
  retry_max_delay_ms: number;
  run_mode: RunMode;
  sport_code: string;
  score_poll_enabled: boolean;
  score_poll_interval_seconds: number;
  status: SourceStatus;
  website?: string;
}

export interface SourceOptions {
  adapters: Array<{ code: string; label: string; supported_sports: string[] }>;
  sports: Array<{ code: string; canonical_name: string; category: string }>;
  credential_storages: CredentialStorage[];
  health_statuses: HealthStatus[];
  run_modes: RunMode[];
  statuses: SourceStatus[];
}

export interface SourceCapability {
  competition_limit?: null | string;
  delivery_methods: string[];
  expected_latency_ms?: null | number;
  id?: null | number;
  languages: string[];
  last_verified_at?: null | string;
  notes?: null | string;
  package_limit?: null | string;
  resource: string;
  support_status: CapabilityStatus;
  updated_at?: null | string;
}

export interface CapabilityOptions {
  delivery_methods: string[];
  resources: string[];
  sources: Array<{ code: string; id: number; name: string }>;
  support_statuses: CapabilityStatus[];
}

export type SourceCapabilityPayload = Omit<SourceCapability, 'id' | 'resource' | 'updated_at'>;

export function getSourceOptions() {
  return requestClient.get<SourceOptions>('/suppliers/options');
}

export function getSources(params: Record<string, unknown>) {
  return requestClient.get<{
    current_page: number;
    data: Source[];
    per_page: number;
    total: number;
  }>('/suppliers', { params });
}

export function getSource(id: number) {
  return requestClient.get<{ source: Source }>(`/suppliers/${id}`);
}

export function createSource(payload: SourcePayload) {
  return requestClient.post<{ source: Source }>('/suppliers', payload);
}

export function updateSource(id: number, payload: SourcePayload) {
  return requestClient.put<{ source: Source }>(`/suppliers/${id}`, payload);
}

export function changeSourceStatus(id: number, status: 'active' | 'disabled' | 'suspended') {
  return requestClient.post<{ source: Source }>(`/suppliers/${id}/${status}`);
}

export function probeSource(id: number, type: 'connection' | 'sample_pull') {
  return requestClient.post<{
    test: {
      duration_ms: number;
      error_code?: null | string;
      http_status?: null | number;
      id: number;
      item_count?: null | number;
      status: 'failed' | 'succeeded';
      tested_at: string;
      type: 'connection' | 'sample_pull';
    };
  }>(`/suppliers/${id}/probe`, { type });
}

export function getCapabilityOptions() {
  return requestClient.get<CapabilityOptions>('/supplier-capabilities/options');
}

export function getSourceCapabilities(sourceId: number) {
  return requestClient.get<{
    data: SourceCapability[];
    source: { code: string; id: number; name: string };
  }>('/supplier-capabilities', { params: { source_id: sourceId } });
}

export function updateSourceCapability(sourceId: number, resource: string, payload: SourceCapabilityPayload) {
  return requestClient.put<{ capability: SourceCapability }>(
    `/supplier-capabilities/${sourceId}/${resource}`,
    payload,
  );
}
