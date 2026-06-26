import { ComponentFixture, TestBed } from '@angular/core/testing';

import { HeaderImage } from './header-image';

describe('HeaderImage', () => {
  let component: HeaderImage;
  let fixture: ComponentFixture<HeaderImage>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
    }).compileComponents();

    fixture = TestBed.createComponent(HeaderImage);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});