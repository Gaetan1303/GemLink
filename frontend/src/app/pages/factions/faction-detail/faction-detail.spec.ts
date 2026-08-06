import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FactionDetail } from './faction-detail';

describe('FactionDetail', () => {
  let component: FactionDetail;
  let fixture: ComponentFixture<FactionDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FactionDetail],
    }).compileComponents();

    fixture = TestBed.createComponent(FactionDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
