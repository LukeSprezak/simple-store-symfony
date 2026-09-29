import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { Page, pageParams } from '../../../shared/http/api';
import { Order, StatusChange } from '../domain/order';

// Orders of the logged-in customer only.
@Injectable({ providedIn: 'root' })
export class OrderApi {
  private readonly http = inject(HttpClient);

  list(limit: number, after: string | null): Observable<Page<Order>> {
    return this.http.get<Page<Order>>('/api/order', { params: pageParams(limit, after) });
  }

  history(orderId: string): Observable<StatusChange[]> {
    return this.http
      .get<Page<StatusChange>>(`/api/order/${orderId}/status-history`, { params: { limit: 100 } })
      .pipe(map((page) => page.items));
  }
}
