import { requestClient } from '#/api/request';

export type StandingTableRow = Record<string, any> & { id: number; revision: number; rows: Record<string, any>[] };
export type StandingOptions = Record<'groups' | 'rounds' | 'seasons' | 'stages' | 'teams', Record<string, any>[]>;

export const getStandingOptions = () => requestClient.get<{ data: StandingOptions }>('/standing-tables/options');
export const getStandingTables = (seasonId: number) => requestClient.get<{ data: StandingTableRow[] }>('/standing-tables', { params: { season_id: seasonId } });
export const getStandingTable = (id: number) => requestClient.get<{ data: StandingTableRow }>(`/standing-tables/${id}`);
export const createStandingTable = (data: Record<string, unknown>) => requestClient.post<{ data: StandingTableRow }>('/standing-tables', data);
export const publishStandingTable = (id: number, data: Record<string, unknown>) => requestClient.put<{ data: StandingTableRow }>(`/standing-tables/${id}`, data);
