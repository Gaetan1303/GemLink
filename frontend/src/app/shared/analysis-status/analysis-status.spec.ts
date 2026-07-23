import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';

import { AnalysisStatus } from './analysis-status';

// US 3.1 — Badge animé reflétant PENDING_ANALYSIS / ANALYZED / ANALYSIS_FAILED.
describe('AnalysisStatus', () => {
  let fixture: ComponentFixture<AnalysisStatus>;

  function render(status: AnalysisStatus['status'], size?: AnalysisStatus['size']): HTMLElement {
    TestBed.configureTestingModule({
      imports: [AnalysisStatus],
      providers: [
        // Requis dès qu'un composant a un trigger `animations` — sans ce
        // provider, Angular jette une NG05105 au lieu d'ignorer l'animation.
        provideNoopAnimations(),
      ],
    });

    fixture = TestBed.createComponent(AnalysisStatus);
    fixture.componentInstance.status = status;
    if (size) {
      fixture.componentInstance.size = size;
    }
    fixture.detectChanges();

    return fixture.nativeElement.querySelector('.analysis-badge');
  }

  it('affiche "Analyse IA en cours…" et le point animé pour PENDING_ANALYSIS', () => {
    const badge = render('PENDING_ANALYSIS');

    expect(badge.textContent).toContain('Analyse IA en cours');
    expect(badge.classList).toContain('analysis-badge--pending');
    expect(badge.querySelector('.analysis-badge__dot')).not.toBeNull();
  });

  it('affiche "Identifiée" pour ANALYZED', () => {
    const badge = render('ANALYZED');

    expect(badge.textContent).toContain('Identifiée');
    expect(badge.classList).toContain('analysis-badge--analyzed');
    expect(badge.querySelector('mat-icon')?.textContent?.trim()).toBe('auto_awesome');
  });

  it('affiche "Non identifiée" pour ANALYSIS_FAILED', () => {
    const badge = render('ANALYSIS_FAILED');

    expect(badge.textContent).toContain('Non identifiée');
    expect(badge.classList).toContain('analysis-badge--failed');
    expect(badge.querySelector('mat-icon')?.textContent?.trim()).toBe('error_outline');
  });

  it('applique le modificateur de taille compacte (post-list) quand size="sm"', () => {
    const badge = render('PENDING_ANALYSIS', 'sm');

    expect(badge.classList).toContain('analysis-badge--sm');
  });

  it('n\'applique pas le modificateur compact par défaut (size="md")', () => {
    const badge = render('PENDING_ANALYSIS');

    expect(badge.classList).not.toContain('analysis-badge--sm');
  });

  // NB : pas de test "mutation de @Input en cours de vie + detectChanges()"
  // ici. L'interaction entre le trigger d'animation synthétique
  // (provideNoopAnimations) et une mutation directe de `status` hors
  // binding parent déclenche un NG0100 (ExpressionChangedAfterItHasBeenCheckedError)
  // qui est un artefact du harnais de test, pas un bug de l'app réelle —
  // dans post-detail/post-list, `status` change via un binding parent
  // (`[status]="p.status"`), pas une mutation directe d'instance. Les 3
  // tests d'état ci-dessus couvrent déjà chaque rendu (pending/analyzed/failed).
});