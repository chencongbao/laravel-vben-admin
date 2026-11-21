import { requestClient } from '#/api/request';
export const dataApplications = (params: any) => requestClient.get<any>('/data-applications', { params });
export const fieldProvenances = (params: any) => requestClient.get<any>('/field-provenances', { params });
export const executeDataApplication = (conflictId: number, data: any) => requestClient.post<any>(`/data-applications/conflicts/${conflictId}/execute`, data);
