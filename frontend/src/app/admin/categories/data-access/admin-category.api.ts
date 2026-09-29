import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { AdminCategory, CategoryPayload } from '../domain/category';

const BASE_URL = '/api/admin/category';

@Injectable({ providedIn: 'root' })
export class AdminCategoryApi {
  private readonly http = inject(HttpClient);

  tree(): Observable<AdminCategory[]> {
    return this.http.get<{ items: AdminCategory[] }>(BASE_URL).pipe(map(({ items }) => items));
  }

  create(payload: CategoryPayload): Observable<void> {
    return this.http.post<void>(BASE_URL, payload);
  }

  update(id: string, payload: CategoryPayload): Observable<void> {
    return this.http.put<void>(`${BASE_URL}/${id}`, payload);
  }

  remove(id: string): Observable<void> {
    return this.http.delete<void>(`${BASE_URL}/${id}`);
  }
}
