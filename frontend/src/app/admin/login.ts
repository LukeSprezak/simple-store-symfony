import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AdminAuthService } from '../auth';

@Component({
  selector: 'app-admin-login',
  imports: [FormsModule],
  host: { class: 'nova' },
  template: `
    <div class="wrapper">
      <div class="brand">
        <span class="brand-mark"></span>
        Example Shop <span class="brand-tag">Staff</span>
      </div>
      <form class="nv-card" (ngSubmit)="submit()">
        <h1>Welcome back!</h1>
        <label>
          Email address
          <input name="email" type="email" [(ngModel)]="email" required />
        </label>
        <label>
          Password
          <input name="password" type="password" [(ngModel)]="password" required />
        </label>
        @if (error()) {
          <p class="nv-error">{{ error() }}</p>
        }
        <button class="nv-btn" type="submit"><span class="material-symbols-rounded" aria-hidden="true">login</span>Log in</button>
      </form>
    </div>
  `,
  styles: `
    .wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 96px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 32px;
      font-size: 22px;
      font-weight: 800;
    }

    .brand-mark {
      width: 26px;
      height: 26px;
      border: 5px solid var(--nv-primary);
      border-radius: 50%;
    }

    .brand-tag {
      padding: 2px 10px;
      border-radius: 9999px;
      background: var(--nv-primary);
      color: #fff;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    form {
      width: 100%;
      max-width: 400px;
      padding: 32px;
    }

    h1 {
      text-align: center;
    }

    label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 20px;
      font-weight: 700;
    }

    input {
      height: 38px;
      padding: 0 12px;
      border: 1px solid var(--nv-border);
      border-radius: 6px;
      background: var(--nv-header);
      color: var(--nv-text);
      font: inherit;
      font-weight: 400;
    }

    input:focus {
      outline: 2px solid var(--nv-primary);
      outline-offset: 0;
    }

    button {
      width: 100%;
    }
  `,
})
export class AdminLogin {
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);
  protected email = '';
  protected password = '';
  protected readonly error = signal('');

  protected submit(): void {
    this.auth.login(this.email, this.password).subscribe({
      next: () => this.router.navigateByUrl('/admin'),
      error: (error: HttpErrorResponse) => this.error.set(error.error?.message ?? 'Invalid credentials.'),
    });
  }
}
