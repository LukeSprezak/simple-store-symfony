import { inject, Injectable, signal } from '@angular/core';
import { MessageApi } from '../data-access/message.api';

// Shared by the sidebar badge and the message list, which refreshes it after status changes.
@Injectable({ providedIn: 'root' })
export class UnreadMessagesStore {
  private readonly api = inject(MessageApi);
  readonly count = signal(0);

  refresh(): void {
    this.api.unreadCount().subscribe((count) => this.count.set(count));
  }
}
