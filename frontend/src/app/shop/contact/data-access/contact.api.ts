import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ContactMessage } from '../domain/contact-message';

@Injectable({ providedIn: 'root' })
export class ContactApi {
  private readonly http = inject(HttpClient);

  // Public endpoint: works without logging in.
  send(message: ContactMessage): Observable<void> {
    return this.http.post<void>('/api/contact', message);
  }
}
