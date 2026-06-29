import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LatestDiscoveries } from './latest-discoveries';

describe('LatestDiscoveries', () => {
  let component: LatestDiscoveries;
  let fixture: ComponentFixture<LatestDiscoveries>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LatestDiscoveries],
    }).compileComponents();

    fixture = TestBed.createComponent(LatestDiscoveries);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
