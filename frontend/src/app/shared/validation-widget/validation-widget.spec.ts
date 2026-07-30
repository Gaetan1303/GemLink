import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ValidationWidget } from './validation-widget';

describe('ValidationWidget', () => {
  let component: ValidationWidget;
  let fixture: ComponentFixture<ValidationWidget>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ValidationWidget],
    }).compileComponents();

    fixture = TestBed.createComponent(ValidationWidget);
    fixture.componentRef.setInput('postId', 'post-test-uuid');
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
