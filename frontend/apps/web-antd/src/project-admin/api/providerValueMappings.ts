import { requestClient } from '#/api/request';

export const valueMappingOptions = () => requestClient.get<any>('/provider-value-mappings/options');
export const providerValueMappings = (params: any) => requestClient.get<any>('/provider-value-mappings', { params });
export const reviewValueMapping = (id: number, data: any) => requestClient.post(`/provider-value-mappings/${id}/review`, data);
