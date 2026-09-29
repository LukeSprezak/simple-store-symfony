import { CurrencyPipe } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from './cart';

interface Product {
  id: string;
  name: string;
  description: string;
  priceInCents: number;
  stockQuantity: number;
}

interface ProductPage {
  items: Product[];
  nextCursor: string | null;
}

@Component({
  selector: 'app-products',
  imports: [CurrencyPipe, RouterLink],
  host: {
    '(window:scroll)': 'onScroll()',
    '(window:wheel)': 'onScroll($event)',
    '(window:touchmove)': 'onScroll()',
  },
  template: `
    <main class="container">
      <section class="hero">
        <h1>Welcome to Example Shop</h1>
        <p class="lead">Tech gear for your desk and beyond.</p>
        <button class="btn btn-primary" type="button" (click)="heading.scrollIntoView({ behavior: 'smooth' })">
          <span class="material-symbols-rounded" aria-hidden="true">storefront</span>
          Shop now
        </button>
      </section>
      <h1 #heading>Products</h1>
      @if (error()) {
        <p class="error">{{ error() }}</p>
      }
      <section class="grid">
        @for (product of products(); track product.id) {
          <article class="panel card" [style.animation-delay.ms]="($index % 12) * 60">
            <h2><a [routerLink]="['/products', product.id]">{{ product.name }}</a></h2>
            <p class="description">{{ product.description }}</p>
            <div class="footer">
              <strong class="price">{{ product.priceInCents / 100 | currency }}</strong>
              @if (product.stockQuantity > 0) {
                <span class="stock">In stock</span>
              } @else {
                <span class="stock out">Out of stock</span>
              }
            </div>
            <button
              class="btn btn-primary add"
              type="button"
              [disabled]="product.stockQuantity === 0 || adding() === product.id"
              (click)="add(product)"
            >
              <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
              Add to cart
            </button>
          </article>
        } @empty {
          <p>No products.</p>
        }
      </section>
      <div #sentinel class="more">
        @if (loading()) {
          <span class="spinner"></span>
        }
      </div>
    </main>
  `,
  styles: `
    .hero {
      margin-bottom: 48px;
      padding: 64px 24px;
      border: 2px solid var(--primary);
      border-radius: 12px;
      background: var(--primary-soft);
      text-align: center;
    }

    .hero h1 {
      margin-bottom: 12px;
      font-size: 44px;
    }

    .lead {
      margin: 0 0 32px;
      color: var(--muted);
      font-size: 20px;
    }

    .hero .btn {
      height: 48px;
      padding: 0 28px;
      font-size: 16px;
    }

    h1 {
      scroll-margin-top: 24px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 20px;
    }

    .card {
      display: flex;
      flex-direction: column;
      padding: 20px;
      border-width: 1px;
      border-color: var(--border);
      box-shadow: var(--shadow);
      animation: appear 0.5s ease-out both;
    }

    @keyframes appear {
      from {
        opacity: 0;
        transform: translateY(16px);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .card {
        animation: none;
      }
    }

    h2 {
      margin-bottom: 8px;
    }

    h2 a {
      color: inherit;
      text-decoration: none;
    }

    h2 a:hover {
      color: var(--primary);
    }

    .description {
      flex: 1;
      margin: 0 0 16px;
      color: var(--muted);
    }

    .footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .price {
      color: var(--primary);
      font: 700 20px Ubuntu, sans-serif;
    }

    .stock {
      padding: 2px 10px;
      border-radius: 6px;
      background: var(--primary-soft);
      color: var(--primary);
      font-size: 12px;
      font-weight: 600;
    }

    .stock.out {
      background: #fee2e2;
      color: var(--danger);
    }

    .add {
      margin-top: 16px;
    }

    .add:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .more {
      display: flex;
      justify-content: center;
      min-height: 48px;
      margin-top: 24px;
    }

    .spinner {
      width: 28px;
      height: 28px;
      border: 3px solid var(--border);
      border-top-color: var(--primary);
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
export class Products {
  private readonly http = inject(HttpClient);
  private readonly cart = inject(CartService);
  private readonly sentinel = viewChild.required<ElementRef<HTMLElement>>('sentinel');
  protected readonly products = signal<Product[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly loading = signal(false);
  protected readonly adding = signal<string | null>(null);
  protected readonly error = signal('');

  constructor() {
    this.load();
  }

  // Loads only on user scroll intent (not on visibility), so the first 3 rows stay alone even when they don't fill the screen.
  protected onScroll(event?: WheelEvent): void {
    if (event && event.deltaY <= 0) {
      return;
    }

    if (this.nextCursor() && this.sentinel().nativeElement.getBoundingClientRect().top < innerHeight + 200) {
      this.load();
    }
  }

  private load(): void {
    if (this.loading()) {
      return;
    }

    const after = this.nextCursor();
    this.loading.set(true);
    this.http.get<ProductPage>('/api/product', { params: after ? { limit: 12, after } : { limit: 12 } }).subscribe((page) => {
      this.products.update((products) => [...products, ...page.items]);
      this.nextCursor.set(page.nextCursor);
      this.loading.set(false);
    });
  }

  protected add(product: Product): void {
    this.adding.set(product.id);
    this.error.set('');
    this.cart.add(product.id).subscribe({
      next: () => this.adding.set(null),
      error: (error: HttpErrorResponse) => {
        this.adding.set(null);
        this.error.set(error.error?.error ?? `Could not add ${product.name} to the cart.`);
      },
    });
  }
}
