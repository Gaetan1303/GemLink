import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PipelineResult } from './pipeline-result';
import { PublicationIdentification } from '../../core/services/post';

const RESULT: PublicationIdentification = {
  nom: 'Quartz',
  categorie: 'Silicate',
  durete: 7,
  systemeCristallin: 'Trigonal',
  composition: 'SiO2',
  description: 'Un spécimen de quartz.',
  confidence: 0.92,
  isHighConfidence: true,
  detectorConfidence: 0.96,
  detections: [
    { nom: 'Quartz', confidence: 0.92, detectorConfidence: 0.96, bbox: [10, 20, 80, 100] },
    { nom: 'Améthyste', confidence: 0.81, detectorConfidence: 0.84, bbox: [90, 20, 160, 110] },
  ],
  modelVersion: {
    yolo: 'yolov8-stone-v2',
    vit: 'vit-stones-v4',
    clip: 'clip-vit-b-32-openai',
  },
};

describe('PipelineResult', () => {
  let component: PipelineResult;
  let fixture: ComponentFixture<PipelineResult>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PipelineResult],
    }).compileComponents();

    fixture = TestBed.createComponent(PipelineResult);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('result', RESULT);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('affiche chaque crop avec les confiances YOLO et ViT', () => {
    const element: HTMLElement = fixture.nativeElement;
    const detections = element.querySelectorAll('.detection');

    expect(detections).toHaveLength(2);
    expect(element.textContent).toContain('2 pierres détectées');
    expect(element.textContent).toContain('Quartz');
    expect(element.textContent).toContain('Améthyste');
    expect(element.textContent).toContain('96%');
    expect(element.textContent).toContain('92%');
  });

  it('trace les versions des trois modèles', () => {
    const versions = Array.from<HTMLElement>(fixture.nativeElement.querySelectorAll('.stage code'))
      .map((element) => element.textContent?.trim());

    expect(versions).toEqual(['yolov8-stone-v2', 'vit-stones-v4', 'clip-vit-b-32-openai']);
  });
});
