import { CurrencyPipe, DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, input, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { finalize, switchMap, take, takeWhile, timer } from 'rxjs';
import { statusLabel } from '../../../../shared/domain/order-status';
import { apiErrorMessage } from '../../../../shared/http/api';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { Spinner } from '../../../../shared/ui/spinner/spinner';
import { AdminOrderApi } from '../../data-access/admin-order.api';
import { AdminOrder, isDestructive, orderNumber, StatusChange, transitionIcon } from '../../domain/order';
import { StatusBadge } from '../../ui/status-badge/status-badge';

// History is projected asynchronously from the outbox: poll every 2 s, for at most 30 s.
const HISTORY_POLL_INTERVAL_MS = 2000;
const HISTORY_POLL_ATTEMPTS = 15;

@Component({
  selector: 'app-admin-order-details',
  imports: [CentsPipe, CurrencyPipe, DatePipe, RouterLink, Spinner, StatusBadge],
  templateUrl: './order-details.html',
  styleUrl: './order-details.css',
})
export class AdminOrderDetails implements OnInit {
  readonly id = input.required<string>();
  private readonly api = inject(AdminOrderApi);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly label = statusLabel;
  protected readonly icon = transitionIcon;
  protected readonly destructive = isDestructive;
  protected readonly number = orderNumber;
  protected readonly order = signal<AdminOrder | null>(null);
  protected readonly history = signal<StatusChange[]>([]);
  protected readonly changing = signal(false);
  protected readonly refreshing = signal(false);
  protected readonly error = signal('');

  ngOnInit(): void {
    this.loadOrder();
    this.api.history(this.id()).subscribe((items) => this.history.set(items));
  }

  protected changeStatus(transition: string): void {
    this.changing.set(true);
    this.error.set('');
    this.api.changeStatus(this.id(), transition).subscribe({
      next: () => {
        this.changing.set(false);
        this.loadOrder();
        this.pollHistory();
      },
      error: (error: HttpErrorResponse) => {
        this.changing.set(false);
        this.error.set(apiErrorMessage(error, 'Could not change the order status.'));
      },
    });
  }

  private loadOrder(): void {
    this.api.get(this.id()).subscribe((order) => this.order.set(order));
  }

  // Stops as soon as a new entry shows up.
  private pollHistory(): void {
    const known = this.history().length;
    this.refreshing.set(true);
    timer(0, HISTORY_POLL_INTERVAL_MS)
      .pipe(
        take(HISTORY_POLL_ATTEMPTS),
        switchMap(() => this.api.history(this.id())),
        takeWhile((items) => items.length <= known, true),
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.refreshing.set(false)),
      )
      .subscribe((items) => this.history.set(items));
  }
}
