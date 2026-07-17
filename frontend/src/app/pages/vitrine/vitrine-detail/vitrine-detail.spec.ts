import { ComponentFixture, TestBed } from '@angular/core/testing';

import { VitrineDetail } from './vitrine-detail';

describe('VitrineDetail', () => {
  let component: VitrineDetail;
  let fixture: ComponentFixture<VitrineDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VitrineDetail],
    }).compileComponents();

    fixture = TestBed.createComponent(VitrineDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
