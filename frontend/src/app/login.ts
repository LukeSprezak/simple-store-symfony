import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from './auth';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  template: `
    <form (ngSubmit)="submit()">
      <input name="email" type="email" placeholder="Email" [(ngModel)]="email" required />
      <input name="password" type="password" placeholder="Password" [(ngModel)]="password" required />
      <button type="submit">Log in</button>
      @if (error()) {
        <p>{{ error() }}</p>
      }
    </form>
  `,
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  protected email = '';
  protected password = '';
  protected readonly error = signal('');

  protected submit(): void {
    this.auth.login(this.email, this.password).subscribe({
      next: () => this.router.navigateByUrl('/'),
      error: () => this.error.set('Invalid credentials.'),
    });
  }
}
