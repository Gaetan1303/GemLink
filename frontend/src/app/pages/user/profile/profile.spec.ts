import { ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { of } from 'rxjs';
import { Profile } from './profile';
import { AuthService } from '../../../core/services/auth';
import { ProfileService } from '../../../core/services/profile';

describe('Profile', () => {
  let component: Profile;
  let fixture: ComponentFixture<Profile>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Profile, HttpClientTestingModule],
      providers: [
        { provide: ActivatedRoute, useValue: { paramMap: of(convertToParamMap({ id: 'user-1' })) } },
        { provide: AuthService, useValue: { currentUser: () => null } },
        { provide: ProfileService, useValue: { getProfile: () => of({ id: 'user-1', username: 'gemme', avatarUrl: null, bio: null, level: 1, badges: [], posts: [] }), getPoints: () => of({ total: 0, transactions: [] }) } },
      ],
    }).compileComponents();
    fixture = TestBed.createComponent(Profile);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => expect(component).toBeTruthy());
});
