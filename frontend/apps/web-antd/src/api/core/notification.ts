import { requestClient } from '#/api/request';

export interface AdminNotificationRecord {
  code: string;
  expires_at?: null | string;
  icon?: null | string;
  id: string;
  is_read: boolean;
  link?: null | string;
  message?: null | string;
  message_key?: null | string;
  metadata: Record<string, unknown>;
  parameters: Record<string, unknown>;
  published_at: string;
  severity: 'error' | 'info' | 'success' | 'warning';
  source: string;
  title?: null | string;
  title_key?: null | string;
  type: string;
}

export interface AdminNotificationPage {
  current_page: number;
  data: AdminNotificationRecord[];
  last_page: number;
  per_page: number;
  total: number;
}

export function getNotificationsApi(params: {
  page: number;
  per_page: number;
}) {
  return requestClient.get<AdminNotificationPage>('/notifications', { params });
}

export function getLatestNotificationsApi(limit = 10) {
  return requestClient.get<{ data: AdminNotificationRecord[] }>(
    '/notifications/latest',
    { params: { limit } },
  );
}

export function getNotificationUnreadCountApi() {
  return requestClient.get<{ count: number }>('/notifications/unread-count');
}

export function markNotificationReadApi(id: string) {
  return requestClient.post(`/notifications/${id}/read`);
}

export function markAllNotificationsReadApi() {
  return requestClient.post('/notifications/read-all');
}

export function hideNotificationApi(id: string) {
  return requestClient.delete(`/notifications/${id}`);
}

export function clearNotificationsApi() {
  return requestClient.delete('/notifications');
}
