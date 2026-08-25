import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';

import { FactionDetail } from './faction-detail';

describe('FactionDetail', () => {
  let component: FactionDetail;
  let fixture: ComponentFixture<FactionDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FactionDetail, RouterTestingModule],
    }).compileComponents();

    fixture = TestBed.createComponent(FactionDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
