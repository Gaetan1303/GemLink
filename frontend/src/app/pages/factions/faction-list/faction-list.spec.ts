import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FactionList } from './faction-list';

describe('FactionList', () => {
  let component: FactionList;
  let fixture: ComponentFixture<FactionList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FactionList],
    }).compileComponents();

    fixture = TestBed.createComponent(FactionList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
