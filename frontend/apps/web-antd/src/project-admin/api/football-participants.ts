import { requestClient } from '#/api/request';
export type ParticipantResource='teams'|'players'|'coaches'|'referees'|'venues'|'team-seasons'|'player-team-tenures';
export type ParticipantRow=Record<string,any>&{id:number};
export interface ParticipantOptions { countries:any[]; seasons:any[]; teams:any[]; players:any[]; team_types:string[]; genders:string[]; age_groups:string[]; positions:string[] }
export const getParticipantOptions=()=>requestClient.get<ParticipantOptions>('/football-participants/options');
export const getParticipantRows=(resource:ParticipantResource,params:Record<string,unknown>)=>requestClient.get<{data:ParticipantRow[]}>(`/football-participants/${resource}`,{params});
export const createParticipantRow=(resource:ParticipantResource,payload:Record<string,unknown>)=>requestClient.post(`/football-participants/${resource}`,payload);
export const updateParticipantRow=(resource:ParticipantResource,id:number,payload:Record<string,unknown>)=>requestClient.put(`/football-participants/${resource}/${id}`,payload);
export const archiveParticipantRow=(resource:ParticipantResource,id:number)=>requestClient.delete(`/football-participants/${resource}/${id}`);
