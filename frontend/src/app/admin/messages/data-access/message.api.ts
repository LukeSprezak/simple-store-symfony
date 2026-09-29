import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { Page, pageParams } from '../../../shared/http/api';
import { Message } from '../domain/message';

const BASE_URL = '/api/admin/contact-message';

@Injectable({ providedIn: 'root' })
export class MessageApi {
  private readonly http = inject(HttpClient);

  list(limit: number, after: string | null): Observable<Page<Message>> {
    return this.http.get<Page<Message>>(BASE_URL, { params: pageParams(limit, after) });
  }

  unreadCount(): Observable<number> {
    return this.http.get<{ count: number }>(`${BASE_URL}/unread-count`).pipe(map(({ count }) => count));
  }

  markRead(id: string): Observable<void> {
    return this.http.post<void>(`${BASE_URL}/${id}/read`, null);
  }

  markUnread(id: string): Observable<void> {
    return this.http.delete<void>(`${BASE_URL}/${id}/read`);
  }
}
