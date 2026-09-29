import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { AdminOrderApi } from '../../data-access/admin-order.api';
import { AdminOrder, orderNumber } from '../../domain/order';
import { StatusBadge } from '../../ui/status-badge/status-badge';

const PAGE_SIZE = 25;

@Component({
  selector: 'app-admin-order-list',
  imports: [CentsPipe, CurrencyPipe, DatePipe, RouterLink, StatusBadge],
  templateUrl: './order-list.html',
  styleUrl: './order-list.css',
})
export class AdminOrderList {
  private readonly api = inject(AdminOrderApi);
  protected readonly number = orderNumber;
  protected readonly orders = signal<AdminOrder[]>([]);
  protected readonly nextCursor = signal<string | null>(null);

  constructor() {
    this.load();
  }

  protected load(): void {
    this.api.list(PAGE_SIZE, this.nextCursor()).subscribe((page) => {
      this.orders.update((orders) => [...orders, ...page.items]);
      this.nextCursor.set(page.nextCursor);
    });
  }
}
