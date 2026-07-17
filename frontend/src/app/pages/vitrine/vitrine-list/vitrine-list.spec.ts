import { ComponentFixture, TestBed } from '@angular/core/testing';

import { VitrineList } from './vitrine-list';

describe('VitrineList', () => {
  let component: VitrineList;
  let fixture: ComponentFixture<VitrineList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VitrineList],
    }).compileComponents();

    fixture = TestBed.createComponent(VitrineList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
