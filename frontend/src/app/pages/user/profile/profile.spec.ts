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
        {
          provide: ActivatedRoute,
          useValue: { paramMap: of(convertToParamMap({ id: 'user-1' })) },
        },
        { provide: AuthService, useValue: { currentUser: () => null } },
        {
          provide: ProfileService,
          useValue: {
            getProfile: () =>
              of({
                id: 'user-1',
                username: 'gemme',
                avatarUrl: null,
                bio: null,
                trustScore: 82,
                level: 1,
                progression: {
                  level: 1,
                  name: 'Novice',
                  minPoints: 0,
                  nextLevel: 2,
                  nextLevelName: 'Initié',
                  nextLevelMinPoints: 100,
                  progressPercent: 0,
                },
                badges: [],
                posts: [],
              }),
            getPoints: () => of({ total: 0, transactions: [] }),
          },
        },
      ],
    }).compileComponents();
    fixture = TestBed.createComponent(Profile);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => expect(component).toBeTruthy());

  it('affiche le niveau courant et la progression calculée par le serveur', () => {
    const content = fixture.nativeElement.textContent as string;
    const progressBar = fixture.nativeElement.querySelector('mat-progress-bar');

    expect(content).toContain('Niveau 1');
    expect(content).toContain('Progression vers le niveau 2');
    expect(progressBar.getAttribute('aria-label')).toBe('0% de progression vers le niveau suivant');
  });

  it('affiche le Trust Score public sans permettre sa modification', () => {
    const content = fixture.nativeElement.textContent as string;
    const meter = fixture.nativeElement.querySelector('meter');

    expect(content).toContain('82/100');
    expect(content).toContain('ne peut pas être modifié manuellement');
    expect(meter.value).toBe(82);
    expect(fixture.nativeElement.querySelector('[formControlName="trustScore"]')).toBeNull();
  });
});
