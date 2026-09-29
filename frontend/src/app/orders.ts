import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

interface Order {
  id: string;
  status: string;
  createdAt: string;
  totalAmountInCents: number;
}

interface StatusChange {
  eventId: string;
  transition: string;
  fromStatus: string;
  toStatus: string;
  recordedAt: string;
}

interface Page<T> {
  items: T[];
  nextCursor: string | null;
}

@Component({
  selector: 'app-orders',
  imports: [CurrencyPipe, DatePipe, RouterLink],
  template: `
    <main class="container">
      <h1>Orders</h1>
      <section class="panel">
        @for (order of orders(); track order.id) {
          <div class="order">
            <button class="row" type="button" (click)="toggle(order.id)">
              <span class="id">#{{ order.id.slice(-8) }}</span>
              <span class="date">{{ order.createdAt | date: 'medium' }}</span>
              <span class="status">{{ label(order.status) }}</span>
              <strong class="total">{{ order.totalAmountInCents / 100 | currency }}</strong>
              <span class="chevron" [class.open]="expanded() === order.id">›</span>
            </button>
            @if (expanded() === order.id) {
              <ol class="timeline">
                <li>
                  <strong>Created</strong>
                  <span>{{ order.createdAt | date: 'medium' }}</span>
                </li>
                @for (change of history()[order.id] ?? []; track change.eventId) {
                  <li>
                    <strong>{{ label(change.fromStatus) }} → {{ label(change.toStatus) }}</strong>
                    <span>{{ change.recordedAt | date: 'medium' }}</span>
                  </li>
                }
              </ol>
            }
          </div>
        } @empty {
          <div class="empty">
            <p>You have no orders yet.</p>
            <a class="btn btn-primary" routerLink="/"><span class="material-symbols-rounded" aria-hidden="true">storefront</span>Browse products</a>
          </div>
        }
      </section>
      @if (nextCursor()) {
        <div class="more">
          <button class="btn" type="button" (click)="load()"><span class="material-symbols-rounded" aria-hidden="true">expand_more</span>Load more</button>
        </div>
      }
    </main>
  `,
  styles: `
    .row {
      display: flex;
      align-items: center;
      gap: 24px;
      width: 100%;
      padding: 16px 0;
      border: 0;
      border-bottom: 1px solid var(--border);
      background: none;
      color: var(--text);
      font: inherit;
      text-align: left;
      cursor: pointer;
    }

    .id {
      font-weight: 700;
    }

    .date {
      flex: 1;
      color: var(--muted);
    }

    .status {
      padding: 2px 10px;
      border-radius: 6px;
      background: var(--primary-soft);
      color: var(--primary);
      font-size: 12px;
      font-weight: 600;
    }

    .total {
      min-width: 100px;
      text-align: right;
    }

    .chevron {
      font-size: 20px;
      transition: transform 0.15s;
    }

    .chevron.open {
      transform: rotate(90deg);
    }

    .timeline {
      margin: 0;
      padding: 16px 0 16px 24px;
      border-bottom: 1px solid var(--border);
      list-style: none;
    }

    .timeline li {
      position: relative;
      display: flex;
      flex-direction: column;
      padding: 0 0 12px 20px;
      border-left: 2px solid var(--border);
    }

    .timeline li::before {
      content: '';
      position: absolute;
      top: 4px;
      left: -7px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--primary);
    }

    .timeline span {
      color: var(--muted);
    }

    .empty {
      text-align: center;
    }

    a.btn {
      text-decoration: none;
    }

    .more {
      margin-top: 24px;
      text-align: center;
    }
  `,
})
export class Orders {
  private readonly http = inject(HttpClient);
  protected readonly orders = signal<Order[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly expanded = signal<string | null>(null);
  protected readonly history = signal<Record<string, StatusChange[]>>({});

  constructor() {
    this.load();
  }

  protected load(): void {
    const after = this.nextCursor();
    this.http.get<Page<Order>>('/api/order', { params: after ? { limit: 20, after } : { limit: 20 } }).subscribe((page) => {
      this.orders.update((orders) => [...orders, ...page.items]);
      this.nextCursor.set(page.nextCursor);
    });
  }

  protected toggle(orderId: string): void {
    if (this.expanded() === orderId) {
      this.expanded.set(null);
      return;
    }

    this.expanded.set(orderId);
    this.http
      .get<Page<StatusChange>>(`/api/order/${orderId}/status-history`, { params: { limit: 100 } })
      .subscribe((page) => this.history.update((history) => ({ ...history, [orderId]: page.items })));
  }

  protected label(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1).replaceAll('_', ' ');
  }
}
