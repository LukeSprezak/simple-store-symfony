import { computed, inject, Injectable, signal } from '@angular/core';
import { map, Observable } from 'rxjs';
import { CartApi } from '../data-access/cart.api';
import { Cart, countItems, isActive } from '../domain/cart';

const CART_KEY = 'cartId';

// The backend has no "my carts" endpoint, so the current cart id is remembered in the browser.
@Injectable({ providedIn: 'root' })
export class CartStore {
  private readonly api = inject(CartApi);
  private readonly cartId = signal(localStorage.getItem(CART_KEY));
  readonly cart = signal<Cart | null>(null);
  readonly itemCount = computed(() => countItems(this.cart()));

  load(): void {
    const cartId = this.cartId();
    if (!cartId) {
      this.cart.set(null);
      return;
    }

    this.api.get(cartId).subscribe({
      next: (cart) => (isActive(cart) ? this.cart.set(cart) : this.clear()),
      error: () => this.clear(),
    });
  }

  add(productId: string, quantity: number): Observable<void> {
    return this.api.add(this.cartId(), productId, quantity).pipe(
      map((cartId) => {
        localStorage.setItem(CART_KEY, cartId);
        this.cartId.set(cartId);
        this.load();
      }),
    );
  }

  remove(productId: string): void {
    this.api.remove(this.cartId()!, productId).subscribe(() => this.load());
  }

  placeOrder(): Observable<void> {
    return this.api.placeOrder(this.cartId()!).pipe(map(() => this.clear()));
  }

  clear(): void {
    localStorage.removeItem(CART_KEY);
    this.cartId.set(null);
    this.cart.set(null);
  }
}
