import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface AdminDashboard { posts24h: number; aiAnalyses24h: number; activeUsers7d: number; communityValidationRate: number; topMinerals: { name: string; count: number }[]; fineTuningJobs: FineTuningJob[]; generatedAt: string; }
export interface AdminUser { id: string; username: string; email: string; role: 'user' | 'expert' | 'moderator' | 'admin'; status: 'active' | 'banned' | 'pending_validation'; trustScore: number; bannedReason: string | null; bannedUntil: string | null; createdAt: string; }
export interface AdminUserPage { items: AdminUser[]; page: number; limit: number; total: number; }
export interface ModelVersion { id: string; name: string; status: 'training' | 'active' | 'deprecated'; accuracy: number | null; f1Score: number | null; }
export interface FineTuningJob { id: string; status: 'pending' | 'running' | 'completed' | 'failed'; progress: number; minTrustScore: number; model: ModelVersion; error: string | null; }
export interface AdminPointsScale { postCreated: number; likeReceived: number; validationSubmitted: number; validationConsensusConfirmed: number; }
export interface AdminValidationSettings { consensusThreshold: number; datasetCandidateTrustThreshold: number; points: AdminPointsScale; }
export type AdminBadgeCondition = 'POST_COUNT' | 'VALIDATION_COUNT' | 'STONE_IDENTIFICATION_COUNT' | 'MINERAL_IDENTIFICATION_COUNT';
export interface AdminBadge { id: string; name: string; description: string | null; conditionType: AdminBadgeCondition; conditionValue: number; pierreId: string | null; }
export interface AdminBadgePayload { name: string; description: string | null; conditionType: AdminBadgeCondition; conditionValue: number; pierreId?: string | null; }

@Injectable({ providedIn: 'root' })
export class Admin {
  readonly #http = inject(HttpClient);
  readonly #url = `${environment.apiUrl}/api/admin`;
  getDashboard(): Observable<AdminDashboard> { return this.#http.get<AdminDashboard>(`${this.#url}/dashboard`); }
  getUsers(): Observable<AdminUserPage> { return this.#http.get<AdminUserPage>(`${this.#url}/users`, { params: { limit: 100 } }); }
  changeRole(id: string, role: AdminUser['role']): Observable<AdminUser> { return this.#http.patch<AdminUser>(`${this.#url}/users/${id}/role`, { role }); }
  ban(id: string, reason: string, until: string | null): Observable<AdminUser> { return this.#http.patch<AdminUser>(`${this.#url}/users/${id}/ban`, { reason, until }); }
  unban(id: string): Observable<AdminUser> { return this.#http.patch<AdminUser>(`${this.#url}/users/${id}/unban`, {}); }
  startFineTuning(minTrustScore: number, versionName: string): Observable<FineTuningJob> { return this.#http.post<FineTuningJob>(`${this.#url}/models/fine-tuning`, { minTrustScore, versionName }); }
  getVitVersions(): Observable<ModelVersion[]> { return this.#http.get<ModelVersion[]>(`${this.#url}/models/vit`); }
  activateVit(id: string): Observable<ModelVersion> { return this.#http.post<ModelVersion>(`${this.#url}/models/vit/${id}/activate`, {}); }
  getValidationSettings(): Observable<AdminValidationSettings> { return this.#http.get<AdminValidationSettings>(`${this.#url}/validation-settings`); }
  updateValidationSettings(settings: Pick<AdminValidationSettings, 'points'>): Observable<AdminValidationSettings> { return this.#http.patch<AdminValidationSettings>(`${this.#url}/validation-settings`, settings); }
  getBadges(): Observable<AdminBadge[]> { return this.#http.get<AdminBadge[]>(`${this.#url}/badges`); }
  createBadge(payload: AdminBadgePayload): Observable<AdminBadge> { return this.#http.post<AdminBadge>(`${this.#url}/badges`, payload); }
  deleteBadge(id: string): Observable<void> { return this.#http.delete<void>(`${this.#url}/badges/${id}`); }
}
