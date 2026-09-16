import { requestClient } from '#/api/request';

export interface DemoResponse {
  status: 'ok';
}

export function getDemoApi() {
  return requestClient.get<DemoResponse>('/demo');
}
