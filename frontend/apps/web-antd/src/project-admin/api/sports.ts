import { requestClient } from '#/api/request';

export interface SportRow {
  id: number; code: string; name_zh: string | null; canonical_name: string;
  enabled: boolean; sort: number; sources_count: number; referenced: boolean;
  code_locked: boolean; deletable: boolean;
  supported_adapters: { code: string; label: string }[];
}
export const getSports = (params: Record<string, unknown>) => requestClient.get<{ data: SportRow[]; total: number }>('/sports', { params });
export const createSport = (data: Record<string, unknown>) => requestClient.post('/sports', data);
export const updateSport = (id: number, data: Record<string, unknown>) => requestClient.put(`/sports/${id}`, data);
export const changeSportStatus = (id: number, enabled: boolean) => requestClient.post(`/sports/${id}/status`, { enabled });
export const deleteSport = (id: number) => requestClient.delete(`/sports/${id}`);
