import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, Signal, signal, WritableSignal } from '@angular/core';
import { map, Observable } from 'rxjs';
import { decodeJwt } from './jwt';

abstract class Session {
  private readonly http = inject(HttpClient);
  readonly token: WritableSignal<string | null>;
  readonly email: Signal<string | undefined>;

  constructor(
    private readonly storageKey: string,
    private readonly loginUrl: string,
  ) {
    this.token = signal(localStorage.getItem(storageKey));
    this.email = computed(() => {
      const token = this.token();

      return token ? decodeJwt(token).email : undefined;
    });
  }

  login(email: string, password: string): Observable<void> {
    return this.http.post<{ token: string }>(this.loginUrl, { email, password }).pipe(map(({ token }) => this.setToken(token)));
  }

  setToken(token: string): void {
    localStorage.setItem(this.storageKey, token);
    this.token.set(token);
  }

  logout(): void {
    localStorage.removeItem(this.storageKey);
    this.token.set(null);
  }
}

@Injectable({ providedIn: 'root' })
export class ShopSession extends Session {
  constructor() {
    super('token', '/api/login_check');
  }
}

@Injectable({ providedIn: 'root' })
export class AdminSession extends Session {
  constructor() {
    super('adminToken', '/api/admin/login_check');
  }
}
