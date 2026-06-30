import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { MentionsLegales } from './mentions-legales';

describe('MentionsLegales', () => {
  let component: MentionsLegales;
  let fixture: ComponentFixture<MentionsLegales>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MentionsLegales],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(MentionsLegales);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
