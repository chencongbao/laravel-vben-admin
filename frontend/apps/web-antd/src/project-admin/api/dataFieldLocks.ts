import { requestClient } from '#/api/request';
export const dataFieldLocks = (params: any) => requestClient.get<any>('/data-field-locks', { params });
export const createDataFieldLock = (data: any) => requestClient.post<any>('/data-field-locks', data);
export const releaseDataFieldLock = (id: number, data: any) => requestClient.post<any>(`/data-field-locks/${id}/release`, data);
