import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { signal } from '@angular/core';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { Home } from './home';
import { AuthService } from '../../core/services/auth';
import { CookieConsentService } from '../../core/services/cookie-consent.service';
import { PostService } from '../../core/services/post';

describe('Home', () => {
  let component: Home;
  let fixture: ComponentFixture<Home>;

  beforeEach(async () => {
    vi.stubGlobal('IntersectionObserver', class {
      observe(): void {}
      disconnect(): void {}
    });
    await TestBed.configureTestingModule({
      imports: [Home],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { currentUser: signal(null) } },
        { provide: CookieConsentService, useValue: { getConsentStatus: () => of(false) } },
        { provide: PostService, useValue: { listPosts: () => of({ items: [], limit: 20, nextCursor: null, hasNextPage: false }) } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Home);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  afterEach(() => vi.unstubAllGlobals());

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
