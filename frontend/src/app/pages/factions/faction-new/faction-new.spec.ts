import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FactionNew } from './faction-new';

describe('FactionNew', () => {
  let component: FactionNew;
  let fixture: ComponentFixture<FactionNew>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FactionNew],
    }).compileComponents();

    fixture = TestBed.createComponent(FactionNew);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
