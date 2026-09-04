import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { ModerationReport, ReportService } from '../../../core/services/report';
import { Moderation } from './moderation';

describe('Moderation', () => {
  let fixture: ComponentFixture<Moderation>;
  const report: ModerationReport = {
    id: 'report-1', reasonType: 'SPAM', description: 'Publicité répétitive', status: 'PENDING',
    createdAt: '2026-08-03T10:00:00+00:00', reportCount: 3,
    reporter: { id: 'user-1', username: 'Quartz' },
    reasonDetails: [{
      reportId: 'report-1', reasonType: 'SPAM', description: 'Publicité répétitive',
      reporter: { id: 'user-1', username: 'Quartz' }, createdAt: '2026-08-03T10:00:00+00:00',
    }],
    moderationHistory: [{
      id: 'audit-1', moderator: { id: 'moderator-1', username: 'Opale' },
      action: 'REPORT_REJECTED', target: { type: 'PUBLICATION', id: 'post-1' },
      reason: 'Contexte insuffisant', createdAt: '2026-08-01T08:00:00+00:00',
    }],
    publication: {
      id: 'post-1', title: 'Annonce', mediaUrl: '/image.jpg', status: 'AUTO_HIDDEN',
      deletedAt: null, author: 'Auteur',
    },
  };
  const service = { list: vi.fn(), decide: vi.fn() };

  beforeEach(async () => {
    vi.clearAllMocks();
    service.list.mockReturnValue(of({ items: [report] }));
    service.decide.mockReturnValue(of({ ...report, status: 'REJECTED' }));
    await TestBed.configureTestingModule({
      imports: [Moderation],
      providers: [provideRouter([]), { provide: ReportService, useValue: service }],
    }).compileComponents();
    fixture = TestBed.createComponent(Moderation);
    fixture.detectChanges();
  });

  it('affiche le nombre, les motifs et l’historique du signalement', () => {
    const text = fixture.nativeElement.textContent;
    expect(text).toContain('3 signalements');
    expect(text).toContain('Publicité répétitive');
    expect(text).toContain('Signalement rejeté');
    expect(text).toContain('Contexte insuffisant');
  });

  it('envoie la décision et son motif puis retire le signalement traité', () => {
    const textarea: HTMLTextAreaElement = fixture.nativeElement.querySelector('textarea');
    textarea.value = 'Spam commercial confirmé';
    textarea.dispatchEvent(new Event('input'));
    fixture.detectChanges();
    fixture.componentInstance['decide'](report, 'ACCEPTED');
    expect(service.decide).toHaveBeenCalledWith('report-1', 'ACCEPTED', 'Spam commercial confirmé');
    expect(fixture.componentInstance['reports']()).toEqual([]);
    expect(fixture.componentInstance['success']()).toContain('publication retirée');
  });

  it('trie côté client par nombre décroissant de signalements', () => {
    const lessReported = { ...report, id: 'report-2', reportCount: 1 };
    service.list.mockReturnValue(of({ items: [lessReported, report] }));
    const anotherFixture = TestBed.createComponent(Moderation);
    anotherFixture.detectChanges();
    expect(anotherFixture.componentInstance['reports']().map(item => item.id)).toEqual(['report-1', 'report-2']);
  });
});
