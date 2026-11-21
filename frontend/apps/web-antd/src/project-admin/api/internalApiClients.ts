import { requestClient } from '#/api/request';

export const internalApiClients = (params: any) => requestClient.get<any>('/internal-api-clients', { params });
export const createInternalApiClient = (data: any) => requestClient.post<any>('/internal-api-clients', data);
export const rotateInternalApiClient = (id: number, data: any) => requestClient.post<any>(`/internal-api-clients/${id}/rotate`, data);
export const changeInternalApiClientScopes = (id: number, data: any) => requestClient.put<any>(`/internal-api-clients/${id}/scopes`, data);
export const changeInternalApiClientStatus = (id: number, status: 'active' | 'disabled', data: any) => requestClient.post<any>(`/internal-api-clients/${id}/${status}`, data);
