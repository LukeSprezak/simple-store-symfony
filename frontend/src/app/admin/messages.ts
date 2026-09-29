import { DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';

interface Message {
  id: string;
  name: string;
  email: string;
  subject: string;
  message: string;
  createdAt: string;
}

@Component({
  selector: 'app-admin-messages',
  imports: [DatePipe],
  template: `
    <h1>Messages</h1>
    <div class="nv-card">
      <table>
        <thead>
          <tr>
            <th>From</th>
            <th>Subject</th>
            <th>Received</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @for (message of messages(); track message.id) {
            <tr class="row" (click)="toggle(message.id)">
              <td>
                <strong>{{ message.name }}</strong>
                <span class="muted">{{ message.email }}</span>
              </td>
              <td>{{ message.subject }}</td>
              <td class="muted">{{ message.createdAt | date: 'medium' }}</td>
              <td class="right">
                <span class="chevron material-symbols-rounded" [class.open]="expanded() === message.id" aria-hidden="true">expand_more</span>
              </td>
            </tr>
            @if (expanded() === message.id) {
              <tr class="body">
                <td colspan="4">
                  <p>{{ message.message }}</p>
                  <a class="nv-btn" [href]="'mailto:' + message.email + '?subject=Re: ' + message.subject">
                    <span class="material-symbols-rounded" aria-hidden="true">mail</span>
                    Reply by email
                  </a>
                </td>
              </tr>
            }
          } @empty {
            <tr>
              <td class="empty" colspan="4">No messages yet.</td>
            </tr>
          }
        </tbody>
      </table>
      <div class="pagination">
        <span class="muted">{{ messages().length }} loaded</span>
        @if (nextCursor()) {
          <button class="nv-btn nv-btn-outline" type="button" (click)="load()">
            <span class="material-symbols-rounded" aria-hidden="true">expand_more</span>
            Load more
          </button>
        }
      </div>
    </div>
  `,
  styles: `
    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      padding: 10px 16px;
      background: var(--nv-header);
      border-bottom: 1px solid var(--nv-border);
      color: var(--nv-muted);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-align: left;
      text-transform: uppercase;
    }

    td {
      padding: 12px 16px;
      border-bottom: 1px solid var(--nv-border);
      vertical-align: top;
    }

    .row {
      cursor: pointer;
    }

    .row:hover {
      background: var(--nv-header);
    }

    td strong,
    td .muted {
      display: block;
    }

    .muted {
      color: var(--nv-muted);
    }

    .right {
      text-align: right;
    }

    .chevron {
      color: var(--nv-muted);
      transition: transform 0.15s;
    }

    .chevron.open {
      transform: rotate(180deg);
    }

    .body td {
      background: var(--nv-header);
    }

    .body p {
      margin: 0 0 16px;
      white-space: pre-line;
    }

    .body .nv-btn {
      text-decoration: none;
    }

    .empty {
      padding: 32px;
      color: var(--nv-muted);
      text-align: center;
    }

    .pagination {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 16px;
      background: var(--nv-header);
      font-size: 12px;
    }
  `,
})
export class AdminMessages {
  private readonly http = inject(HttpClient);
  protected readonly messages = signal<Message[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly expanded = signal<string | null>(null);

  constructor() {
    this.load();
  }

  protected load(): void {
    const after = this.nextCursor();
    this.http
      .get<{ items: Message[]; nextCursor: string | null }>('/api/admin/contact-message', { params: after ? { limit: 25, after } : { limit: 25 } })
      .subscribe((page) => {
        this.messages.update((messages) => [...messages, ...page.items]);
        this.nextCursor.set(page.nextCursor);
      });
  }

  protected toggle(id: string): void {
    this.expanded.set(this.expanded() === id ? null : id);
  }
}
