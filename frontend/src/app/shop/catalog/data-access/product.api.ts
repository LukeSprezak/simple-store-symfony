import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { Page, pageParams } from '../../../shared/http/api';
import { Product } from '../domain/product';

@Injectable({ providedIn: 'root' })
export class ProductApi {
  private readonly http = inject(HttpClient);

  // `category` accepts a top-level or a subcategory slug; a top-level one includes its subcategories.
  list(limit: number, after: string | null, category?: string): Observable<Page<Product>> {
    return this.http.get<Page<Product>>('/api/product', { params: pageParams(limit, after, { category }) });
  }

  get(id: string): Observable<Product> {
    return this.http.get<Product>(`/api/product/${id}`);
  }
}
