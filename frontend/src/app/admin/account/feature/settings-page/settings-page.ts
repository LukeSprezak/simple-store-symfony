import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule, NgForm } from '@angular/forms';
import { AdminSession } from '../../../../core/auth';
import { AccountApi } from '../../../../shared/account/account';
import { apiErrorMessage } from '../../../../shared/http/api';

@Component({
  selector: 'app-admin-settings-page',
  imports: [FormsModule],
  templateUrl: './settings-page.html',
  styleUrl: './settings-page.css',
})
export class AdminSettingsPage {
  private readonly api = inject(AccountApi);
  private readonly session = inject(AdminSession);
  protected readonly account = toSignal(this.api.get('admin'));
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal('');

  protected changePassword(form: NgForm): void {
    this.saving.set(true);
    this.saved.set(false);
    this.error.set('');
    this.api.changePassword('admin', form.value).subscribe({
      next: ({ token }) => {
        this.session.setToken(token);
        this.saving.set(false);
        this.saved.set(true);
        form.resetForm();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'Could not change the password.'));
      },
    });
  }
}
