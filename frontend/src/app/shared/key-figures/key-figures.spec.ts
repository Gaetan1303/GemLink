import { ComponentFixture, TestBed } from '@angular/core/testing';

import { KeyFigures } from './key-figures';

describe('KeyFigures', () => {
  let component: KeyFigures;
  let fixture: ComponentFixture<KeyFigures>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [KeyFigures],
    }).compileComponents();

    fixture = TestBed.createComponent(KeyFigures);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
