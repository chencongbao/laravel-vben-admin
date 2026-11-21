import { requestClient } from '#/api/request';

export function supplierMonitoringOverview(windowHours: number, qualityDays: number) {
  return requestClient.get('/supplier-monitoring/overview', {
    params: { quality_days: qualityDays, window_hours: windowHours },
  });
}
