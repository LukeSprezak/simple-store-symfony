import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MessageApi } from '../../data-access/message.api';
import { isUnread, Message, replyLink } from '../../domain/message';
import { UnreadMessagesStore } from '../../state/unread-messages.store';

const PAGE_SIZE = 25;

@Component({
  selector: 'app-admin-message-list',
  imports: [DatePipe],
  templateUrl: './message-list.html',
  styleUrl: './message-list.css',
})
export class AdminMessageList {
  private readonly api = inject(MessageApi);
  private readonly unread = inject(UnreadMessagesStore);
  protected readonly replyLink = replyLink;
  protected readonly messages = signal<Message[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly expanded = signal<string | null>(null);

  constructor() {
    this.load();
  }

  protected load(): void {
    this.api.list(PAGE_SIZE, this.nextCursor()).subscribe((page) => {
      this.messages.update((messages) => [...messages, ...page.items]);
      this.nextCursor.set(page.nextCursor);
    });
  }

  // Opening a message marks it as read.
  protected toggle(message: Message): void {
    if (this.expanded() === message.id) {
      this.expanded.set(null);
      return;
    }

    this.expanded.set(message.id);
    if (isUnread(message)) {
      this.api.markRead(message.id).subscribe(() => this.setReadAt(message.id, new Date().toISOString()));
    }
  }

  protected markUnread(message: Message): void {
    this.api.markUnread(message.id).subscribe(() => {
      this.setReadAt(message.id, null);
      this.expanded.set(null);
    });
  }

  private setReadAt(id: string, readAt: string | null): void {
    this.messages.update((messages) => messages.map((message) => (message.id === id ? { ...message, readAt } : message)));
    this.unread.refresh();
  }
}
