import { TestBed } from '@angular/core/testing';

import { VitrineService } from './vitrine';

describe('VitrineService', () => {
  let service: VitrineService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(VitrineService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
