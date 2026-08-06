import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FactionManage } from './faction-manage';

describe('FactionManage', () => {
  let component: FactionManage;
  let fixture: ComponentFixture<FactionManage>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FactionManage],
    }).compileComponents();

    fixture = TestBed.createComponent(FactionManage);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
