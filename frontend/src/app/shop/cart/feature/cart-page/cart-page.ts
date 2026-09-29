import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../../../shared/http/api';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { CartStore } from '../../state/cart.store';

@Component({
  selector: 'app-cart-page',
  imports: [CentsPipe, CurrencyPipe, RouterLink],
  templateUrl: './cart-page.html',
  styleUrl: './cart-page.css',
})
export class CartPage {
  protected readonly cart = inject(CartStore);
  protected readonly placing = signal(false);
  protected readonly placed = signal(false);
  protected readonly error = signal('');

  constructor() {
    this.cart.load();
  }

  protected placeOrder(): void {
    this.placing.set(true);
    this.error.set('');
    this.cart.placeOrder().subscribe({
      next: () => {
        this.placing.set(false);
        this.placed.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.placing.set(false);
        this.error.set(apiErrorMessage(error, 'Could not place the order.'));
      },
    });
  }
}
