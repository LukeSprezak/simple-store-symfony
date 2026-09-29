import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './auth';
import { CartService } from './cart';
import { Footer } from './footer';

@Component({
  selector: 'app-shop',
  imports: [Footer, RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <header class="header">
      <a class="logo" routerLink="/">
        <span class="logo-mark"></span>
        Example Shop
      </a>
      @if (auth.token()) {
        <nav class="menu">
          <a routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">Products</a>
          <a routerLink="/cart" routerLinkActive="active">
            Cart
            @if (cart.itemCount()) {
              <span class="badge">{{ cart.itemCount() }}</span>
            }
          </a>
          <a routerLink="/orders" routerLinkActive="active">Orders</a>
          <a routerLink="/contact" routerLinkActive="active">Contact</a>
        </nav>
        <div class="account">
          <a class="btn icon-btn" routerLink="/settings" routerLinkActive="active" title="Settings" aria-label="Settings">
            <span class="material-symbols-rounded" aria-hidden="true">settings</span>
          </a>
          <button class="btn" type="button" (click)="logout()">
            <span class="material-symbols-rounded" aria-hidden="true">logout</span>
            Log out
          </button>
        </div>
      }
    </header>
    <div class="page">
      <router-outlet />
    </div>
    <app-footer />
  `,
  styles: `
    :host {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .page {
      flex: 1;
    }

    .header {
      display: flex;
      align-items: center;
      gap: 32px;
      height: 72px;
      padding: 0 24px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text);
      font: 500 26px Ubuntu, sans-serif;
      text-decoration: none;
    }

    .menu {
      display: flex;
      flex: 1;
      gap: 8px;
    }

    .menu a {
      padding: 8px 14px;
      border-radius: var(--radius);
      color: var(--text);
      font-weight: 600;
      text-decoration: none;
    }

    .menu a:hover {
      background: var(--primary-soft);
    }

    .menu a.active {
      background: var(--primary-soft);
      color: var(--primary);
    }

    .account {
      display: flex;
      gap: 12px;
    }

    .icon-btn {
      width: 40px;
      padding: 0;
      text-decoration: none;
    }

    .icon-btn.active {
      background: var(--primary-soft);
    }

    .badge {
      margin-left: 4px;
      padding: 0 7px;
      border-radius: 10px;
      background: var(--primary);
      color: #fff;
      font-size: 12px;
    }

    .logo-mark {
      width: 28px;
      height: 28px;
      border: 4px solid var(--primary);
      border-radius: 6px;
      transform: rotate(45deg);
    }
  `,
})
export class Shop {
  protected readonly auth = inject(AuthService);
  protected readonly cart = inject(CartService);
  private readonly router = inject(Router);

  constructor() {
    if (this.auth.token()) {
      this.cart.load();
    }
  }

  protected logout(): void {
    this.auth.logout();
    this.cart.clear();
    this.router.navigateByUrl('/login');
  }
}
