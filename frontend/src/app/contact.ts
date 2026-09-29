import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from './auth';

@Component({
  selector: 'app-contact',
  imports: [FormsModule, RouterLink],
  template: `
    <section class="hero">
      <div class="inner">
        <h1>We're always here to help — get in touch!</h1>
        <p class="lead">
          Have a question about a product, your cart or an order? Send us a message and our team will get back to you.
        </p>
        <div class="actions">
          <button class="btn btn-primary" type="button" (click)="formSection.scrollIntoView({ behavior: 'smooth' })">
            <span class="material-symbols-rounded" aria-hidden="true">description</span>
            Write to us
          </button>
          <a class="btn outline" routerLink="/orders">
            <span class="material-symbols-rounded" aria-hidden="true">receipt_long</span>
            Your orders
          </a>
        </div>
        <p class="support">
          <strong>Customer support:</strong>
          <a href="mailto:support@example.com">support&#64;example.com</a>
        </p>
      </div>
    </section>

    <section class="methods">
      <div class="inner">
        <h2>Choose the most convenient way to reach us</h2>
        <div class="cards">
          <article class="panel card">
            <span class="icon material-symbols-rounded" aria-hidden="true">description</span>
            <h3>Contact form</h3>
            <p>Describe your question in detail and we will reply by email.</p>
            <button class="btn btn-primary" type="button" (click)="formSection.scrollIntoView({ behavior: 'smooth' })">
              Fill in the form
            </button>
          </article>
          <article class="panel card">
            <span class="icon material-symbols-rounded" aria-hidden="true">mail</span>
            <h3>Email</h3>
            <p>Prefer your own mail client? Write to us directly.</p>
            <a class="btn btn-primary" href="mailto:support@example.com">Send an email</a>
          </article>
          <article class="panel card">
            <span class="icon material-symbols-rounded" aria-hidden="true">receipt_long</span>
            <h3>Order status</h3>
            <p>Check the status history of your orders at any time.</p>
            <a class="btn btn-primary" routerLink="/orders">Go to orders</a>
          </article>
        </div>
      </div>
    </section>

    <section class="form-section" #formSection>
      <div class="inner narrow">
        <h2>Contact form</h2>
        <p class="subtitle">All fields are required.</p>
        @if (sent()) {
          <div class="panel sent">
            <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
            <p>Thanks for your message! We'll get back to you by email.</p>
          </div>
        } @else {
          <form #form="ngForm" (ngSubmit)="send(form)">
            <label class="field">
              Name
              <input name="name" maxlength="100" autocomplete="name" ngModel required />
            </label>
            <label class="field">
              Email
              <input name="email" type="email" maxlength="254" autocomplete="email" [ngModel]="auth.email()" required />
            </label>
            <label class="field">
              Subject
              <input name="subject" maxlength="150" ngModel required />
            </label>
            <label class="field">
              Message
              <textarea name="message" rows="6" minlength="10" maxlength="5000" ngModel required></textarea>
            </label>
            @if (error()) {
              <p class="error">{{ error() }}</p>
            }
            <div class="submit">
              <button class="btn btn-primary" type="submit" [disabled]="sending()">
                <span class="material-symbols-rounded" aria-hidden="true">send</span>
                Send message
              </button>
            </div>
          </form>
        }
      </div>
    </section>
  `,
  styles: `
    .inner {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 16px;
    }

    .narrow {
      max-width: 560px;
    }

    .hero {
      padding: 64px 0;
      background: var(--text);
      color: #fff;
    }

    .hero h1 {
      max-width: 560px;
      font-size: 40px;
      line-height: 1.15;
    }

    .lead {
      max-width: 560px;
      margin: 0 0 28px;
      color: #e0e7ff;
      font-size: 17px;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      padding-bottom: 32px;
      border-bottom: 1px solid rgb(255 255 255 / 0.2);
    }

    .btn {
      text-decoration: none;
    }

    .btn.outline {
      border-color: #fff;
      box-shadow: none;
      background: transparent;
      color: #fff;
    }

    .support {
      margin: 24px 0 0;
      color: #e0e7ff;
    }

    .support strong {
      display: block;
      color: #fff;
    }

    .support a {
      color: #e0e7ff;
    }

    .methods {
      padding: 56px 0;
    }

    h2 {
      margin-bottom: 32px;
      font-size: 28px;
      text-align: center;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 20px;
    }

    .card {
      display: flex;
      flex-direction: column;
      align-items: center;
      border-width: 1px;
      border-color: var(--border);
      box-shadow: var(--shadow);
      text-align: center;
    }

    .icon {
      display: grid;
      place-items: center;
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: var(--primary-soft);
      color: var(--primary);
      font-size: 26px;
    }

    h3 {
      margin: 16px 0 8px;
      font: 700 18px Ubuntu, sans-serif;
    }

    .card p {
      flex: 1;
      margin: 0 0 20px;
      color: var(--muted);
    }

    .card .btn {
      width: 100%;
    }

    .form-section {
      padding: 56px 0 72px;
      background: var(--surface);
      border-top: 1px solid var(--border);
      scroll-margin-top: 16px;
    }

    .form-section h2 {
      margin-bottom: 4px;
    }

    .subtitle {
      margin: 0 0 32px;
      color: var(--muted);
      text-align: center;
    }

    textarea {
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: inset 2px 2px 0 2px var(--field);
      color: var(--text);
      font: 400 14px 'Open Sans', sans-serif;
      resize: vertical;
    }

    textarea:focus {
      outline: 2px solid var(--primary);
      outline-offset: 1px;
    }

    .submit {
      text-align: center;
    }

    .submit .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .sent {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #16a34a;
      font-weight: 600;
    }

    .sent p {
      margin: 0;
    }
  `,
})
export class Contact {
  private readonly http = inject(HttpClient);
  protected readonly auth = inject(AuthService);
  protected readonly sending = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal('');

  protected send(form: NgForm): void {
    this.sending.set(true);
    this.error.set('');
    this.http.post<void>('/api/contact', form.value).subscribe({
      next: () => {
        this.sending.set(false);
        this.sent.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(error.error?.error ?? 'Could not send the message.');
      },
    });
  }
}
