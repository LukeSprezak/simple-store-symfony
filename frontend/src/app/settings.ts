import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule, NgForm } from '@angular/forms';
import { AuthService } from './auth';

interface Account {
  email: string;
  username: string;
}

@Component({
  selector: 'app-settings',
  imports: [FormsModule],
  template: `
    <main class="container">
      <h1>Settings</h1>
      <section class="panel">
        <h2>Account</h2>
        @if (account(); as account) {
          <dl>
            <dt>Email</dt>
            <dd>{{ account.email }}</dd>
            <dt>Username</dt>
            <dd>{{ account.username }}</dd>
          </dl>
        }
      </section>

      <form class="panel" #form="ngForm" (ngSubmit)="changePassword(form)">
        <h2>Change password</h2>
        <label class="field">
          Current password
          <input name="currentPassword" type="password" autocomplete="current-password" ngModel required />
        </label>
        <label class="field">
          New password
          <input name="newPassword" type="password" autocomplete="new-password" minlength="8" ngModel required />
        </label>
        @if (error()) {
          <p class="error">{{ error() }}</p>
        }
        @if (saved()) {
          <p class="success">Password changed.</p>
        }
        <button class="btn btn-primary" type="submit" [disabled]="saving()">
          <span class="material-symbols-rounded" aria-hidden="true">lock_reset</span>
          Change password
        </button>
      </form>
    </main>
  `,
  styles: `
    .panel {
      max-width: 560px;
      margin-bottom: 24px;
    }

    dl {
      display: grid;
      grid-template-columns: 120px 1fr;
      gap: 12px;
      margin: 0;
    }

    dt {
      color: var(--muted);
      font-weight: 600;
    }

    dd {
      margin: 0;
    }

    .success {
      color: #16a34a;
      font-weight: 600;
    }

    button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  `,
})
export class Settings {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  protected readonly account = toSignal(this.http.get<Account>('/api/me'));
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal('');

  protected changePassword(form: NgForm): void {
    this.saving.set(true);
    this.saved.set(false);
    this.error.set('');
    this.http.post<{ token: string }>('/api/me/password', form.value).subscribe({
      next: ({ token }) => {
        // The old token is revoked by the password change.
        this.auth.setToken(token);
        this.saving.set(false);
        this.saved.set(true);
        form.resetForm();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(error.error?.error ?? 'Could not change the password.');
      },
    });
  }
}
