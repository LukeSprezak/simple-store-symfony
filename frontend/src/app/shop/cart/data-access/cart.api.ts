import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { Cart } from '../domain/cart';

@Injectable({ providedIn: 'root' })
export class CartApi {
  private readonly http = inject(HttpClient);

  get(cartId: string): Observable<Cart> {
    return this.http.get<Cart>(`/api/cart/${cartId}`);
  }

  // Without a cart id the backend creates a new cart; either way it answers with the cart id.
  add(cartId: string | null, productId: string, quantity: number): Observable<string> {
    const url = cartId ? `/api/cart/add-product/${cartId}` : '/api/cart/add-product';

    return this.http.post<{ cartId: string }>(url, { productId, quantity }).pipe(map((response) => response.cartId));
  }

  remove(cartId: string, productId: string): Observable<void> {
    return this.http.delete<void>('/api/cart/remove-product', { body: { cartId, productId } });
  }

  placeOrder(cartId: string): Observable<void> {
    return this.http.post<void>(`/api/cart/${cartId}/convert`, null);
  }
}
