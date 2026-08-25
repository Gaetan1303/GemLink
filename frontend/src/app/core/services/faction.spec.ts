import { TestBed } from '@angular/core/testing';

import { FactionService } from './faction';

describe('Faction', () => {
  let service: FactionService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(FactionService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
