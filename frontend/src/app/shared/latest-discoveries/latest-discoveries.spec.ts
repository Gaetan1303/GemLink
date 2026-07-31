import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { LatestDiscoveries } from './latest-discoveries';
import { PostService } from '../../core/services/post';

describe('LatestDiscoveries', () => {
  let component: LatestDiscoveries;
  let fixture: ComponentFixture<LatestDiscoveries>;

  beforeEach(async () => {
    vi.stubGlobal('IntersectionObserver', class {
      observe(): void {}
      disconnect(): void {}
    });
    await TestBed.configureTestingModule({
      imports: [LatestDiscoveries],
      providers: [
        provideRouter([]),
        { provide: PostService, useValue: { listPosts: () => of({ items: [], limit: 20, nextCursor: null, hasNextPage: false }) } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(LatestDiscoveries);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  afterEach(() => vi.unstubAllGlobals());

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
