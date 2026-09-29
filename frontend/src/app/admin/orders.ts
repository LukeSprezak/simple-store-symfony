import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { statusLabel, statusTone } from './status';

export interface AdminOrder {
  id: string;
  status: string;
  customerEmail: string;
  createdAt: string;
  totalAmountInCents: number;
  transitions: string[];
}

interface Page {
  items: AdminOrder[];
  nextCursor: string | null;
}

@Component({
  selector: 'app-admin-orders',
  imports: [CurrencyPipe, DatePipe, RouterLink],
  template: `
    <h1>Orders</h1>
    <div class="nv-card">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Status</th>
            <th class="right">Total</th>
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @for (order of orders(); track order.id) {
            <tr>
              <td><a class="nv-link" [routerLink]="['/admin/orders', order.id]">{{ order.id.slice(-8) }}</a></td>
              <td>{{ order.customerEmail }}</td>
              <td><span class="nv-badge" [class]="tone(order.status)">{{ label(order.status) }}</span></td>
              <td class="right">{{ order.totalAmountInCents / 100 | currency }}</td>
              <td class="muted">{{ order.createdAt | date: 'medium' }}</td>
              <td class="right"><a class="view" [routerLink]="['/admin/orders', order.id]" title="View"><span class="material-symbols-rounded" aria-hidden="true">visibility</span></a></td>
            </tr>
          } @empty {
            <tr>
              <td class="empty" colspan="6">No orders matched the given criteria.</td>
            </tr>
          }
        </tbody>
      </table>
      <div class="pagination">
        <span class="muted">{{ orders().length }} loaded</span>
        @if (nextCursor()) {
          <button class="nv-btn nv-btn-outline" type="button" (click)="load()"><span class="material-symbols-rounded" aria-hidden="true">expand_more</span>Load more</button>
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
    }

    tbody tr:hover {
      background: var(--nv-header);
    }

    .right {
      text-align: right;
    }

    .muted {
      color: var(--nv-muted);
    }

    .view {
      color: var(--nv-muted);
      font-weight: 700;
      text-decoration: none;
    }

    .view:hover {
      color: var(--nv-primary);
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
export class AdminOrders {
  private readonly http = inject(HttpClient);
  protected readonly orders = signal<AdminOrder[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly label = statusLabel;
  protected readonly tone = statusTone;

  constructor() {
    this.load();
  }

  protected load(): void {
    const after = this.nextCursor();
    this.http.get<Page>('/api/admin/order', { params: after ? { limit: 25, after } : { limit: 25 } }).subscribe((page) => {
      this.orders.update((orders) => [...orders, ...page.items]);
      this.nextCursor.set(page.nextCursor);
    });
  }
}
