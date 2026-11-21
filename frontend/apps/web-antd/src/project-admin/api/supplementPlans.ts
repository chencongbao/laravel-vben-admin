import { requestClient } from '#/api/request';

export const supplementPlans = (params: any) => requestClient.get<any>('/supplement-plans', { params });
export const supplementPlan = (id: number) => requestClient.get<any>(`/supplement-plans/${id}`);
export const createSupplementPlan = (data: any) => requestClient.post<any>('/supplement-plans', data);
export const reviewSupplementPlan = (id: number, data: any) => requestClient.post<any>(`/supplement-plans/${id}/review`, data);
