import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { Page, pageParams } from '../../../shared/http/api';
import { AdminOrder, StatusChange } from '../domain/order';

// Orders of all customers; requires a staff session.
@Injectable({ providedIn: 'root' })
export class AdminOrderApi {
  private readonly http = inject(HttpClient);

  list(limit: number, after: string | null): Observable<Page<AdminOrder>> {
    return this.http.get<Page<AdminOrder>>('/api/admin/order', { params: pageParams(limit, after) });
  }

  get(orderId: string): Observable<AdminOrder> {
    return this.http.get<AdminOrder>(`/api/admin/order/${orderId}`);
  }

  history(orderId: string): Observable<StatusChange[]> {
    return this.http
      .get<Page<StatusChange>>(`/api/admin/order/${orderId}/status-history`, { params: { limit: 100 } })
      .pipe(map((page) => page.items));
  }

  changeStatus(orderId: string, transition: string): Observable<void> {
    return this.http.post<void>(`/api/admin/order/${orderId}/status`, { transition });
  }
}
