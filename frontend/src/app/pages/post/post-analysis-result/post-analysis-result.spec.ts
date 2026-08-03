import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PublicationIdentification } from '../../../core/services/post';
import { PostAnalysisResult } from './post-analysis-result';

describe('PostAnalysisResult', () => {
  let fixture: ComponentFixture<PostAnalysisResult>;

  const identification: PublicationIdentification = {
    id: 'stone-1',
    nom: 'Améthyste',
    categorie: 'Quartz',
    durete: 7,
    systemeCristallin: 'Trigonal',
    composition: 'SiO₂',
    description: 'Un quartz violet.',
    confidence: 0.92,
    confidenceThreshold: 0.4,
    isHighConfidence: true,
    isUncertain: false,
    catalogueUrl: '/api/pierres/stone-1',
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({ imports: [PostAnalysisResult] }).compileComponents();
    fixture = TestBed.createComponent(PostAnalysisResult);
  });

  function render(status: 'PENDING_ANALYSIS' | 'ANALYZED' | 'COMMUNITY_VALIDATED', result: PublicationIdentification | null): HTMLElement {
    fixture.componentRef.setInput('status', status);
    fixture.componentRef.setInput('identification', result);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  it('affiche Analyse en cours et un skeleton pendant PENDING_ANALYSIS (CA-1)', () => {
    const element = render('PENDING_ANALYSIS', null);

    expect(element.querySelector('.analysis-skeleton')).toBeTruthy();
    expect(element.textContent).toContain('Analyse en cours');
    expect(element.querySelector('.identification-card')).toBeNull();
  });

  it('affiche le label, le score et le lien catalogue après analyse (CA-2)', () => {
    const element = render('ANALYZED', identification);
    const link = element.querySelector<HTMLAnchorElement>('.catalogue-link');

    expect(element.textContent).toContain('Améthyste');
    expect(element.textContent).toContain('92%');
    expect(link?.getAttribute('href')).toBe('/api/pierres/stone-1');
  });

  it('affiche le badge lorsque le résultat est validé par la communauté (CA-2)', () => {
    const element = render('COMMUNITY_VALIDATED', identification);

    expect(element.querySelector('.community-badge')?.textContent).toContain('Validé par la communauté');
  });

  it('utilise le seuil administrable pour signaler une identification incertaine (CA-3)', () => {
    const element = render('ANALYZED', {
      ...identification,
      confidence: 0.39,
      confidenceThreshold: 0.4,
      isHighConfidence: false,
      isUncertain: undefined,
    });

    expect(element.querySelector('.identification-warning')?.textContent).toContain('Identification incertaine');
  });

  it('ne signale pas un score supérieur au seuil configuré (CA-3)', () => {
    const element = render('ANALYZED', {
      ...identification,
      confidence: 0.41,
      confidenceThreshold: 0.4,
      isHighConfidence: false,
      isUncertain: undefined,
    });

    expect(element.querySelector('.identification-warning')).toBeNull();
  });
});
