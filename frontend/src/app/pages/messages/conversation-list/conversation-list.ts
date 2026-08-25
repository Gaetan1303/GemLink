import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from '../../../shared/shared-module';
import { ChatService, Conversation } from '../../../core/services/chat';
import { Router } from '@angular/router';

@Component({
  selector: 'app-conversation-list',
  imports: [CommonModule, SharedModule],
  templateUrl: './conversation-list.html',
  styleUrls: ['./conversation-list.scss'],
})
export class ConversationList {
  private readonly chatService = inject(ChatService);
  private readonly router = inject(Router);

  protected readonly conversations = signal<Conversation[]>([]);
  protected readonly isLoading = signal(false);
  protected readonly loadError = signal<string | null>(null);

  constructor() {
    this.load();
  }

  load(): void {
    this.isLoading.set(true);
    this.chatService.list().subscribe({
      next: r => { this.conversations.set(r.items ?? []); this.isLoading.set(false); },
      error: err => { this.loadError.set(err?.error?.message ?? 'Impossible de charger les conversations'); this.isLoading.set(false); }
    });
  }

  open(id: string): void { this.router.navigate(['/messages', id]); }
}
