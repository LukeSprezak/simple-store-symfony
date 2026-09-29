import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ShopSession } from '../../../../core/auth';

@Component({
  selector: 'app-login-page',
  imports: [FormsModule],
  templateUrl: './login-page.html',
  styleUrl: './login-page.css',
})
export class LoginPage {
  private readonly session = inject(ShopSession);
  private readonly router = inject(Router);
  protected email = '';
  protected password = '';
  protected readonly error = signal('');

  protected submit(): void {
    this.session.login(this.email, this.password).subscribe({
      next: () => this.router.navigateByUrl('/'),
      error: () => this.error.set('Invalid credentials.'),
    });
  }
}
