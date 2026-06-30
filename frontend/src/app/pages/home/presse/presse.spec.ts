import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Presse } from './presse';

describe('Presse', () => {
  let component: Presse;
  let fixture: ComponentFixture<Presse>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Presse],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Presse);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
