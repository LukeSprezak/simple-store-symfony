import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AdminSession } from '../../../../core/auth';

@Component({
  selector: 'app-admin-login-page',
  imports: [FormsModule],
  templateUrl: './login-page.html',
  styleUrl: './login-page.css',
  host: { class: 'nova' },
})
export class AdminLoginPage {
  private readonly session = inject(AdminSession);
  private readonly router = inject(Router);
  protected email = '';
  protected password = '';
  protected readonly error = signal('');

  protected submit(): void {
    this.session.login(this.email, this.password).subscribe({
      next: () => this.router.navigateByUrl('/admin'),
      // The staff login answers non-staff accounts with a message of its own, e.g. "Staff access only."
      error: (error: HttpErrorResponse) => this.error.set(error.error?.message ?? 'Invalid credentials.'),
    });
  }
}
