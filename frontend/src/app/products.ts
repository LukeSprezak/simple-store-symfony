import { CurrencyPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';

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
  imports: [CurrencyPipe],
  template: `
    <main class="container">
      <h1>Products</h1>
      <section class="grid">
        @for (product of products(); track product.id) {
          <article class="panel card">
            <h2>{{ product.name }}</h2>
            <p class="description">{{ product.description }}</p>
            <div class="footer">
              <strong class="price">{{ product.priceInCents / 100 | currency }}</strong>
              @if (product.stockQuantity > 0) {
                <span class="stock">In stock: {{ product.stockQuantity }}</span>
              } @else {
                <span class="stock out">Out of stock</span>
              }
            </div>
          </article>
        } @empty {
          <p>No products.</p>
        }
      </section>
      @if (nextCursor()) {
        <div class="more">
          <button class="btn" type="button" (click)="load()">Load more</button>
        </div>
      }
    </main>
  `,
  styles: `
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
    }

    h2 {
      margin-bottom: 8px;
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

    .more {
      margin-top: 24px;
      text-align: center;
    }
  `,
})
export class Products {
  private readonly http = inject(HttpClient);
  protected readonly products = signal<Product[]>([]);
  protected readonly nextCursor = signal<string | null>(null);

  constructor() {
    this.load();
  }

  protected load(): void {
    const after = this.nextCursor();
    this.http.get<ProductPage>('/api/product', { params: after ? { limit: 12, after } : { limit: 12 } }).subscribe((page) => {
      this.products.update((products) => [...products, ...page.items]);
      this.nextCursor.set(page.nextCursor);
    });
  }
}
