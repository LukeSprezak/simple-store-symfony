import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule, NgForm } from '@angular/forms';
import { AdminAuthService } from '../auth';

interface Account {
  email: string;
  username: string;
  roles: string[];
}

@Component({
  selector: 'app-admin-settings',
  imports: [FormsModule],
  template: `
    <h1>Settings</h1>
    <div class="nv-card section">
      @if (account(); as account) {
        <div class="detail"><span>Email</span><span>{{ account.email }}</span></div>
        <div class="detail"><span>Username</span><span>{{ account.username }}</span></div>
        <div class="detail">
          <span>Roles</span>
          <span class="roles">
            @for (role of account.roles; track role) {
              <span class="nv-badge">{{ role }}</span>
            }
          </span>
        </div>
      }
    </div>

    <h2>Change password</h2>
    <form class="nv-card section" #form="ngForm" (ngSubmit)="changePassword(form)">
      <label class="detail">
        <span>Current password</span>
        <input name="currentPassword" type="password" autocomplete="current-password" ngModel required />
      </label>
      <label class="detail">
        <span>New password</span>
        <input name="newPassword" type="password" autocomplete="new-password" minlength="8" ngModel required />
      </label>
      <div class="footer">
        @if (error()) {
          <span class="nv-error">{{ error() }}</span>
        }
        @if (saved()) {
          <span class="success">Password changed.</span>
        }
        <button class="nv-btn" type="submit" [disabled]="saving()">
          <span class="material-symbols-rounded" aria-hidden="true">lock_reset</span>
          Change password
        </button>
      </div>
    </form>
  `,
  styles: `
    .section {
      margin-bottom: 32px;
    }

    .detail {
      display: flex;
      align-items: center;
      padding: 16px 24px;
      border-bottom: 1px solid var(--nv-border);
    }

    .detail > span:first-child {
      width: 25%;
      color: var(--nv-muted);
      font-weight: 700;
    }

    .roles {
      display: flex;
      gap: 6px;
    }

    h2 {
      margin: 0 0 16px;
      font: 400 20px 'Nunito Sans', sans-serif;
    }

    input {
      flex: 1;
      max-width: 400px;
      height: 38px;
      padding: 0 12px;
      border: 1px solid var(--nv-border);
      border-radius: 6px;
      background: var(--nv-header);
      color: var(--nv-text);
      font: inherit;
    }

    input:focus {
      outline: 2px solid var(--nv-primary);
      outline-offset: 0;
    }

    .footer {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 16px;
      padding: 12px 24px;
      background: var(--nv-header);
    }

    .success {
      color: var(--nv-success);
      font-weight: 600;
    }
  `,
})
export class AdminSettings {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  protected readonly account = toSignal(this.http.get<Account>('/api/admin/me'));
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal('');

  protected changePassword(form: NgForm): void {
    this.saving.set(true);
    this.saved.set(false);
    this.error.set('');
    this.http.post<{ token: string }>('/api/admin/me/password', form.value).subscribe({
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
