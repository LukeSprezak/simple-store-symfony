import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { ShopSession } from '../../../core/auth';
import { CartStore } from '../../cart';
import { MegaMenu } from '../../catalog';
import { Footer } from '../footer/footer';

@Component({
  selector: 'app-shop-layout',
  imports: [Footer, MegaMenu, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './shop-layout.html',
  styleUrl: './shop-layout.css',
})
export class ShopLayout {
  protected readonly session = inject(ShopSession);
  protected readonly cart = inject(CartStore);
  private readonly router = inject(Router);

  constructor() {
    if (this.session.token()) {
      this.cart.load();
    }
  }

  protected logout(): void {
    this.session.logout();
    this.cart.clear();
    this.router.navigateByUrl('/login');
  }
}
