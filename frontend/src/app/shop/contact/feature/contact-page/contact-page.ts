import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ShopSession } from '../../../../core/auth';
import { apiErrorMessage } from '../../../../shared/http/api';
import { ContactApi } from '../../data-access/contact.api';
import { SUPPORT_EMAIL } from '../../domain/contact-message';

@Component({
  selector: 'app-contact-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './contact-page.html',
  styleUrl: './contact-page.css',
})
export class ContactPage {
  private readonly api = inject(ContactApi);
  protected readonly session = inject(ShopSession);
  protected readonly supportEmail = SUPPORT_EMAIL;
  protected readonly sending = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal('');

  protected send(form: NgForm): void {
    this.sending.set(true);
    this.error.set('');
    this.api.send(form.value).subscribe({
      next: () => {
        this.sending.set(false);
        this.sent.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(apiErrorMessage(error, 'Could not send the message.'));
      },
    });
  }
}
