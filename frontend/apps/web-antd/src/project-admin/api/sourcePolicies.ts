import { requestClient } from '#/api/request';

export const sourcePolicyOptions = () => requestClient.get<any>('/source-policies/options');
export const sourcePolicies = (params: any) => requestClient.get<any>('/source-policies', { params });
export const assessMatchSources = (matchId: number, dataGroup?: string) => requestClient.get<any>(`/source-policies/matches/${matchId}/assessment`, { params: { data_group: dataGroup } });
export const createSourcePolicy = (data: any) => requestClient.post('/source-policies', data);
export const updateSourcePolicy = (id: number, data: any) => requestClient.put(`/source-policies/${id}`, data);
export const deleteSourcePolicy = (id: number, data: any) => requestClient.delete(`/source-policies/${id}`, { data });
