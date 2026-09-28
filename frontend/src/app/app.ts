import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './auth';
import { CartService } from './cart';

@Component({
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
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
