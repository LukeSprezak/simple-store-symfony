import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, input, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { finalize, map, Observable, switchMap, take, takeWhile, timer } from 'rxjs';
import { AdminOrder } from './orders';
import { statusLabel, statusTone, transitionIcon } from './status';

interface StatusChange {
  eventId: string;
  transition: string;
  fromStatus: string;
  toStatus: string;
  recordedAt: string;
}

@Component({
  selector: 'app-admin-order',
  imports: [CurrencyPipe, DatePipe, RouterLink],
  template: `
    <a class="back nv-link" routerLink="/admin/orders">← Orders</a>
    @if (order(); as order) {
      <div class="heading">
        <h1>Order Details: {{ order.id.slice(-8) }}</h1>
        @if (order.transitions.length) {
          <div class="actions">
            @for (transition of order.transitions; track transition) {
              <button
                class="nv-btn"
                [class.nv-btn-danger]="transition === 'cancel' || transition === 'fail_payment'"
                type="button"
                [disabled]="changing()"
                (click)="changeStatus(transition)"
              >
                <span class="material-symbols-rounded" aria-hidden="true">{{ icon(transition) }}</span>
                {{ label(transition) }}
              </button>
            }
          </div>
        }
      </div>
      @if (error()) {
        <p class="nv-error">{{ error() }}</p>
      }
      <div class="nv-card fields">
        <div class="detail"><span>ID</span><span>{{ order.id }}</span></div>
        <div class="detail"><span>Customer</span><span>{{ order.customerEmail }}</span></div>
        <div class="detail">
          <span>Status</span>
          <span><span class="nv-badge" [class]="tone(order.status)">{{ label(order.status) }}</span></span>
        </div>
        <div class="detail"><span>Total</span><span>{{ order.totalAmountInCents / 100 | currency }}</span></div>
        <div class="detail"><span>Created</span><span>{{ order.createdAt | date: 'medium' }}</span></div>
      </div>

      <h2>Status history</h2>
      <div class="nv-card">
        <ol class="timeline">
          <li>
            <strong>Created</strong>
            <span>{{ order.createdAt | date: 'medium' }}</span>
          </li>
          @for (change of history(); track change.eventId) {
            <li>
              <strong>{{ label(change.fromStatus) }} → {{ label(change.toStatus) }}</strong>
              <span>{{ label(change.transition) }} · {{ change.recordedAt | date: 'medium' }}</span>
            </li>
          }
          @if (refreshing()) {
            <li class="pending">
              <strong><span class="spinner"></span>Updating history…</strong>
            </li>
          }
        </ol>
      </div>
    }
  `,
  styles: `
    .back {
      display: inline-block;
      margin-bottom: 12px;
    }

    .heading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 16px;
    }

    .fields {
      margin-bottom: 32px;
    }

    .detail {
      display: flex;
      padding: 16px 24px;
      border-bottom: 1px solid var(--nv-border);
    }

    .detail:last-child {
      border-bottom: 0;
    }

    .detail > span:first-child {
      width: 25%;
      color: var(--nv-muted);
      font-weight: 700;
    }

    h2 {
      margin: 0 0 16px;
      font: 400 20px 'Nunito Sans', sans-serif;
    }

    .timeline {
      margin: 0;
      padding: 24px 24px 12px 40px;
      list-style: none;
    }

    .timeline li {
      position: relative;
      display: flex;
      flex-direction: column;
      padding: 0 0 16px 20px;
      border-left: 2px solid var(--nv-border);
    }

    .timeline li::before {
      content: '';
      position: absolute;
      top: 4px;
      left: -7px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--nv-primary);
    }

    .timeline span {
      color: var(--nv-muted);
    }

    .timeline .pending {
      color: var(--nv-muted);
    }

    .timeline .pending::before {
      background: var(--nv-border);
    }

    .spinner {
      display: inline-block;
      width: 12px;
      height: 12px;
      margin-right: 8px;
      border: 2px solid var(--nv-border);
      border-top-color: var(--nv-primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }
  `,
})
export class AdminOrderDetail implements OnInit {
  readonly id = input.required<string>();
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly order = signal<AdminOrder | null>(null);
  protected readonly history = signal<StatusChange[]>([]);
  protected readonly changing = signal(false);
  protected readonly refreshing = signal(false);
  protected readonly error = signal('');
  protected readonly label = statusLabel;
  protected readonly tone = statusTone;
  protected readonly icon = transitionIcon;

  ngOnInit(): void {
    this.loadOrder();
    this.fetchHistory().subscribe((items) => this.history.set(items));
  }

  protected changeStatus(transition: string): void {
    this.changing.set(true);
    this.error.set('');
    this.http.post<void>(`/api/admin/order/${this.id()}/status`, { transition }).subscribe({
      next: () => {
        this.changing.set(false);
        this.loadOrder();
        this.pollHistory();
      },
      error: (error: HttpErrorResponse) => {
        this.changing.set(false);
        this.error.set(error.error?.error ?? 'Could not change the order status.');
      },
    });
  }

  private loadOrder(): void {
    this.http.get<AdminOrder>(`/api/admin/order/${this.id()}`).subscribe((order) => this.order.set(order));
  }

  // History is projected asynchronously from the outbox, so poll until the new entry shows up (max 30 s).
  private pollHistory(): void {
    const known = this.history().length;
    this.refreshing.set(true);
    timer(0, 2000)
      .pipe(
        take(15),
        switchMap(() => this.fetchHistory()),
        takeWhile((items) => items.length <= known, true),
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.refreshing.set(false)),
      )
      .subscribe((items) => this.history.set(items));
  }

  private fetchHistory(): Observable<StatusChange[]> {
    return this.http
      .get<{ items: StatusChange[] }>(`/api/admin/order/${this.id()}/status-history`, { params: { limit: 100 } })
      .pipe(map((page) => page.items));
  }
}
