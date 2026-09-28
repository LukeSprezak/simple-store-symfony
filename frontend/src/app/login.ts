import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from './auth';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  template: `
    <main class="container">
      <form class="panel" (ngSubmit)="submit()">
        <h1>Log in</h1>
        <label class="field">
          Email
          <input name="email" type="email" [(ngModel)]="email" required />
        </label>
        <label class="field">
          Password
          <input name="password" type="password" [(ngModel)]="password" required />
        </label>
        @if (error()) {
          <p class="error">{{ error() }}</p>
        }
        <button class="btn btn-primary" type="submit">Log in</button>
      </form>
    </main>
  `,
  styles: `
    form {
      max-width: 420px;
      margin: 48px auto 0;
    }

    button {
      width: 100%;
    }
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
