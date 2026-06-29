import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PolitiqueDeConfidentialite } from './politique-de-confidentialite';

describe('PolitiqueDeConfidentialite', () => {
  let component: PolitiqueDeConfidentialite;
  let fixture: ComponentFixture<PolitiqueDeConfidentialite>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PolitiqueDeConfidentialite],
    }).compileComponents();

    fixture = TestBed.createComponent(PolitiqueDeConfidentialite);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
