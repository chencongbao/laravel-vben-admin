import { requestClient } from '#/api/request';
export const dataConflicts = (params: any) => requestClient.get<any>('/data-conflicts', { params });
export const detectDataConflicts = (planId: number) => requestClient.post<any>(`/data-conflicts/detect/${planId}`);
export const resolveDataConflict = (id: number, data: any) => requestClient.post<any>(`/data-conflicts/${id}/resolve`, data);
