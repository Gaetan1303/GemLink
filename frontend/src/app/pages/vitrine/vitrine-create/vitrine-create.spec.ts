import { ComponentFixture, TestBed } from '@angular/core/testing';

import { VitrineCreate } from './vitrine-create';

describe('VitrineCreate', () => {
  let component: VitrineCreate;
  let fixture: ComponentFixture<VitrineCreate>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VitrineCreate],
    }).compileComponents();

    fixture = TestBed.createComponent(VitrineCreate);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
