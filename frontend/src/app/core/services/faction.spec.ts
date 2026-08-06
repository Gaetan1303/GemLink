import { TestBed } from '@angular/core/testing';

import { Faction } from './faction';

describe('Faction', () => {
  let service: Faction;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Faction);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
