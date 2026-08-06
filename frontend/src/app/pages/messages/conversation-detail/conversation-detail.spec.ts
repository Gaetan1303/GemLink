import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ConversationDetail } from './conversation-detail';

describe('ConversationDetail', () => {
  let component: ConversationDetail;
  let fixture: ComponentFixture<ConversationDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ConversationDetail],
    }).compileComponents();

    fixture = TestBed.createComponent(ConversationDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
