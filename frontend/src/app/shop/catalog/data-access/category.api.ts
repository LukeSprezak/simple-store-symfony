import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { CategoryNode } from '../domain/category';

@Injectable({ providedIn: 'root' })
export class CategoryApi {
  private readonly http = inject(HttpClient);

  tree(): Observable<CategoryNode[]> {
    return this.http.get<{ items: CategoryNode[] }>('/api/category').pipe(map(({ items }) => items));
  }
}
