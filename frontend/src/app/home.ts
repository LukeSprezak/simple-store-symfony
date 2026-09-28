import { JsonPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { AuthService } from './auth';

@Component({
  selector: 'app-home',
  imports: [JsonPipe],
  template: `
    <button type="button" (click)="logout()">Log out</button>
    <pre>{{ api() | json }}</pre>
  `,
})
export class Home {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  protected readonly api = toSignal(inject(HttpClient).get('/api'));

  protected logout(): void {
    this.auth.logout();
    this.router.navigateByUrl('/login');
  }
}
