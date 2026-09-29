import { CurrencyPipe } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from './cart';

interface ProductDetail {
  id: string;
  name: string;
  description: string;
  priceInCents: number;
  stockQuantity: number;
}

@Component({
  selector: 'app-product',
  imports: [CurrencyPipe, RouterLink],
  template: `
    @if (product(); as product) {
      <section class="hero">
        <div class="inner">
          <div class="intro">
            <nav class="breadcrumb">
              <a routerLink="/">Products</a>
              <span>/</span>
              <span>{{ product.name }}</span>
            </nav>
            @if (product.stockQuantity > 0) {
              <span class="tag">In stock</span>
            } @else {
              <span class="tag out">Out of stock</span>
            }
            <h1>{{ product.name }}</h1>
            <p class="lead">{{ product.description }}</p>
            <ul class="meta">
              <li>Code {{ product.id.slice(-8).toUpperCase() }}</li>
              <li>Price {{ product.priceInCents / 100 | currency }}</li>
              <li>Order tracking</li>
            </ul>
          </div>
        </div>
      </section>

      <div class="inner body">
        <main>
          <h2>Details worth knowing</h2>
          <ul class="panel details">
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">inventory_2</span>
              {{ product.stockQuantity > 0 ? 'Available to order now' : 'Currently out of stock' }}
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">payments</span>
              {{ product.priceInCents / 100 | currency }} per item
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">sell</span>
              Product code {{ product.id.slice(-8).toUpperCase() }}
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">receipt_long</span>
              Status updates in your orders
            </li>
          </ul>
        </main>

        <aside class="buy panel">
          <strong class="price">{{ product.priceInCents / 100 | currency }}</strong>
          <button
            class="btn btn-primary"
            type="button"
            [disabled]="product.stockQuantity === 0 || adding()"
            (click)="add(product)"
          >
            <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
            Add to cart
          </button>
          @if (added()) {
            <p class="note success">
              <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
              Added to your cart.
            </p>
          } @else if (product.stockQuantity > 0) {
            <p class="note">
              <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
              Available now · stock is reserved once it's in your cart
            </p>
          }
          @if (error()) {
            <p class="error">{{ error() }}</p>
          }
          <div class="links">
            <a class="btn" routerLink="/cart">
              <span class="material-symbols-rounded" aria-hidden="true">shopping_cart</span>
              Go to cart
            </a>
            <a class="btn" routerLink="/">
              <span class="material-symbols-rounded" aria-hidden="true">storefront</span>
              All products
            </a>
          </div>
        </aside>
      </div>
    } @else if (notFound()) {
      <main class="container">
        <section class="panel missing">
          <h1>Product not found</h1>
          <a class="btn btn-primary" routerLink="/">
            <span class="material-symbols-rounded" aria-hidden="true">storefront</span>
            Browse products
          </a>
        </section>
      </main>
    }
  `,
  styles: `
    .inner {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 16px;
    }

    .hero {
      padding: 56px 0;
      background: var(--text);
      color: #fff;
    }

    .intro {
      max-width: 640px;
    }

    .breadcrumb {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      color: #c7d2fe;
      font-size: 13px;
    }

    .breadcrumb a {
      color: #c7d2fe;
      text-decoration: none;
    }

    .breadcrumb a:hover {
      color: #fff;
    }

    .tag {
      display: inline-block;
      padding: 2px 10px;
      border: 1px solid #c7d2fe;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .tag.out {
      border-color: #fca5a5;
      color: #fca5a5;
    }

    h1 {
      margin: 16px 0 12px;
      font-size: 44px;
      line-height: 1.1;
    }

    .lead {
      margin: 0 0 20px;
      color: #e0e7ff;
      font-size: 18px;
    }

    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 20px;
      margin: 0;
      padding: 0;
      color: #c7d2fe;
      list-style: none;
    }

    .meta li + li::before {
      content: '·';
      margin-right: 20px;
    }

    .body {
      display: grid;
      grid-template-columns: 1fr 340px;
      align-items: start;
      gap: 48px;
    }

    main {
      padding: 48px 0;
    }

    h2 {
      font-size: 30px;
    }

    .details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px 32px;
      margin: 0;
      padding: 28px 32px;
      list-style: none;
    }

    .details li {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .details .material-symbols-rounded {
      color: var(--primary);
    }

    .buy {
      position: sticky;
      top: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-top: -240px;
      box-shadow: 4px 4px 0 0 var(--text);
    }

    .price {
      font: 700 36px Ubuntu, sans-serif;
    }

    .buy > .btn {
      height: 52px;
      font-size: 16px;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .note {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      color: var(--muted);
      font-size: 13px;
    }

    .note .material-symbols-rounded {
      color: #16a34a;
      font-size: 18px;
    }

    .note.success {
      color: #16a34a;
      font-weight: 600;
    }

    .error {
      margin: 0;
    }

    .links {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }

    .links .btn {
      padding: 0 12px;
      text-decoration: none;
    }

    .missing {
      text-align: center;
    }

    .missing .btn {
      text-decoration: none;
    }

    @media (max-width: 900px) {
      .body {
        grid-template-columns: 1fr;
      }

      .buy {
        position: static;
        margin-top: 24px;
      }
    }
  `,
})
export class ProductPage implements OnInit {
  readonly id = input.required<string>();
  private readonly http = inject(HttpClient);
  private readonly cart = inject(CartService);
  protected readonly product = signal<ProductDetail | null>(null);
  protected readonly notFound = signal(false);
  protected readonly adding = signal(false);
  protected readonly added = signal(false);
  protected readonly error = signal('');

  ngOnInit(): void {
    this.http.get<ProductDetail>(`/api/product/${this.id()}`).subscribe({
      next: (product) => this.product.set(product),
      error: () => this.notFound.set(true),
    });
  }

  protected add(product: ProductDetail): void {
    this.adding.set(true);
    this.added.set(false);
    this.error.set('');
    this.cart.add(product.id).subscribe({
      next: () => {
        this.adding.set(false);
        this.added.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.adding.set(false);
        this.error.set(error.error?.error ?? `Could not add ${product.name} to the cart.`);
      },
    });
  }
}
