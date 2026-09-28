import { CurrencyPipe } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, Injectable, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { map, Observable } from 'rxjs';

const CART_KEY = 'cartId';

interface CartItem {
  id: string;
  productId: string;
  productName: string;
  unitPriceInCents: number;
  quantity: number;
  totalAmountInCents: number;
}

interface CartView {
  id: string;
  status: string;
  expiresAt: string;
  items: CartItem[];
  totalAmountInCents: number;
}

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly http = inject(HttpClient);
  private readonly cartId = signal(localStorage.getItem(CART_KEY));
  readonly cart = signal<CartView | null>(null);
  readonly itemCount = computed(() => this.cart()?.items.reduce((sum, item) => sum + item.quantity, 0) ?? 0);

  load(): void {
    const cartId = this.cartId();
    if (!cartId) {
      this.cart.set(null);
      return;
    }

    this.http.get<CartView>(`/api/cart/${cartId}`).subscribe({
      next: (cart) => (cart.status === 'active' ? this.cart.set(cart) : this.clear()),
      error: () => this.clear(),
    });
  }

  add(productId: string): Observable<void> {
    const cartId = this.cartId();
    const url = cartId ? `/api/cart/add-product/${cartId}` : '/api/cart/add-product';

    return this.http.post<{ cartId: string }>(url, { productId, quantity: 1 }).pipe(
      map(({ cartId }) => {
        localStorage.setItem(CART_KEY, cartId);
        this.cartId.set(cartId);
        this.load();
      }),
    );
  }

  remove(productId: string): void {
    this.http
      .delete('/api/cart/remove-product', { body: { cartId: this.cartId(), productId } })
      .subscribe(() => this.load());
  }

  placeOrder(): Observable<void> {
    return this.http.post<void>(`/api/cart/${this.cartId()}/convert`, null).pipe(map(() => this.clear()));
  }

  clear(): void {
    localStorage.removeItem(CART_KEY);
    this.cartId.set(null);
    this.cart.set(null);
  }
}

@Component({
  selector: 'app-cart',
  imports: [CurrencyPipe, RouterLink],
  template: `
    <main class="container">
      <h1>Cart</h1>
      @if (cart.cart(); as view) {
        <section class="panel">
          @for (item of view.items; track item.id) {
            <div class="row">
              <div class="name">
                <strong>{{ item.productName }}</strong>
                <span>{{ item.unitPriceInCents / 100 | currency }} × {{ item.quantity }}</span>
              </div>
              <strong>{{ item.totalAmountInCents / 100 | currency }}</strong>
              <button class="btn" type="button" (click)="cart.remove(item.productId)">Remove</button>
            </div>
          } @empty {
            <p>Your cart is empty.</p>
          }
          <div class="total">
            <span>Total</span>
            <strong>{{ view.totalAmountInCents / 100 | currency }}</strong>
          </div>
          @if (error()) {
            <p class="error">{{ error() }}</p>
          }
          @if (view.items.length) {
            <div class="actions">
              <button class="btn btn-primary" type="button" [disabled]="placing()" (click)="placeOrder()">Place order</button>
            </div>
          }
        </section>
      } @else if (placed()) {
        <section class="panel empty">
          <h2>Order placed</h2>
          <p>Thank you! Your order has been created.</p>
          <div class="links">
            <a class="btn" routerLink="/orders">View orders</a>
            <a class="btn btn-primary" routerLink="/">Continue shopping</a>
          </div>
        </section>
      } @else {
        <section class="panel empty">
          <p>Your cart is empty.</p>
          <a class="btn btn-primary" routerLink="/">Browse products</a>
        </section>
      }
    </main>
  `,
  styles: `
    .row {
      display: flex;
      align-items: center;
      gap: 24px;
      padding: 16px 0;
      border-bottom: 1px solid var(--border);
    }

    .name {
      display: flex;
      flex: 1;
      flex-direction: column;
    }

    .name span {
      color: var(--muted);
    }

    .total {
      display: flex;
      justify-content: space-between;
      padding-top: 16px;
      font: 700 20px Ubuntu, sans-serif;
    }

    .total strong {
      color: var(--primary);
    }

    .actions {
      margin-top: 24px;
      text-align: right;
    }

    .actions button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .empty {
      text-align: center;
    }

    a.btn {
      text-decoration: none;
    }

    .links {
      display: flex;
      justify-content: center;
      gap: 12px;
    }
  `,
})
export class Cart {
  protected readonly cart = inject(CartService);
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
        this.error.set(error.error?.error ?? 'Could not place the order.');
      },
    });
  }
}
