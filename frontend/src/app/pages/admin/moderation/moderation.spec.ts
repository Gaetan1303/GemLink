import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { ModerationReport, ReportService } from '../../../core/services/report';
import { Moderation } from './moderation';

describe('Moderation', () => {
  let fixture: ComponentFixture<Moderation>;
  const report: ModerationReport = {
    id: 'report-1', reasonType: 'SPAM', description: null, status: 'PENDING',
    createdAt: new Date().toISOString(), reporter: { id: 'user-1', username: 'Quartz' },
    publication: { id: 'post-1', title: 'Annonce', mediaUrl: '/image.jpg', author: 'Auteur' },
  };
  const service = {
    list: vi.fn().mockReturnValue(of({ items: [report] })),
    decide: vi.fn().mockReturnValue(of({ ...report, status: 'REJECTED' })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Moderation],
      providers: [provideRouter([]), { provide: ReportService, useValue: service }],
    }).compileComponents();
    fixture = TestBed.createComponent(Moderation);
    fixture.detectChanges();
  });

  it('charge les signalements en attente', () => {
    expect(fixture.componentInstance['reports']()).toEqual([report]);
    expect(fixture.nativeElement.textContent).toContain('Annonce');
  });

  it('retire un signalement après décision', () => {
    fixture.componentInstance['decide'](report, 'REJECTED');
    expect(service.decide).toHaveBeenCalledWith('report-1', 'REJECTED');
    expect(fixture.componentInstance['reports']()).toEqual([]);
  });
});
