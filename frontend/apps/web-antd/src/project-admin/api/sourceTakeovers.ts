import { requestClient } from '#/api/request';

export const sourceTakeovers = (params: any) => requestClient.get<any>('/source-takeovers', { params });
export const proposeSourceTakeover = (data: any) => requestClient.post('/source-takeovers', data);
export const approveSourceTakeover = (id: number, data: any) => requestClient.post(`/source-takeovers/${id}/approve`, data);
export const cancelSourceTakeover = (id: number, data: any) => requestClient.post(`/source-takeovers/${id}/cancel`, data);
export const activateSourceTakeover = (id: number, data: any) => requestClient.post(`/source-takeovers/${id}/activate`, data);
export const probeSourceTakeoverRecovery = (id: number, data: any) => requestClient.post(`/source-takeovers/${id}/probe-recovery`, data);
export const closeSourceTakeover = (id: number, data: any) => requestClient.post(`/source-takeovers/${id}/close`, data);
