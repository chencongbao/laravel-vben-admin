import { requestClient } from '#/api/request';

export type ClassificationResource = 'competitions' | 'countries' | 'groups' | 'regions' | 'rounds' | 'seasons' | 'stages';
export type ClassificationRow = Record<string, any> & { id: number; name_en: string };
export interface ClassificationOptions {
  competition_types: string[];
  competitions: Array<{ id: number; name: string }>;
  countries: Array<{ code?: string; id: number; name: string }>;
  genders: string[];
  groups: Array<{ id: number; name: string }>;
  regions: Array<{ code?: string; id: number; name: string }>;
  seasons: Array<{ id: number; name: string }>;
  stages: Array<{ id: number; name: string }>;
}

export function getClassificationOptions() {
  return requestClient.get<ClassificationOptions>('/football-classifications/options');
}

export function getClassificationRows(resource: ClassificationResource, params: Record<string, unknown>) {
  return requestClient.get<{ current_page: number; data: ClassificationRow[]; per_page: number; total: number }>(
    `/football-classifications/${resource}`, { params },
  );
}

export function createClassificationRow(resource: ClassificationResource, payload: Record<string, unknown>) {
  return requestClient.post(`/football-classifications/${resource}`, payload);
}

export function updateClassificationRow(resource: ClassificationResource, id: number, payload: Record<string, unknown>) {
  return requestClient.put(`/football-classifications/${resource}/${id}`, payload);
}

export function archiveClassificationRow(resource: ClassificationResource, id: number) {
  return requestClient.delete(`/football-classifications/${resource}/${id}`);
}
