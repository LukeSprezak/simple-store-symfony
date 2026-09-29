import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { statusLabel } from '../../../../shared/domain/order-status';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { OrderApi } from '../../data-access/order.api';
import { Order, orderNumber, StatusChange } from '../../domain/order';

const PAGE_SIZE = 20;

@Component({
  selector: 'app-order-list',
  imports: [CentsPipe, CurrencyPipe, DatePipe, RouterLink],
  templateUrl: './order-list.html',
  styleUrl: './order-list.css',
})
export class OrderList {
  private readonly api = inject(OrderApi);
  protected readonly label = statusLabel;
  protected readonly number = orderNumber;
  protected readonly orders = signal<Order[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly expanded = signal<string | null>(null);
  protected readonly history = signal<Record<string, StatusChange[]>>({});

  constructor() {
    this.load();
  }

  protected load(): void {
    this.api.list(PAGE_SIZE, this.nextCursor()).subscribe((page) => {
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
    this.api.history(orderId).subscribe((items) => this.history.update((history) => ({ ...history, [orderId]: items })));
  }
}
