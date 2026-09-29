import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export interface Account {
  email: string;
  username: string;
  roles: string[];
}

export interface PasswordChange {
  currentPassword: string;
  newPassword: string;
}

// The same account endpoints exist for both sessions; the path picks the token the interceptor sends.
const BASE_URL = { shop: '/api/me', admin: '/api/admin/me' } as const;

export type AccountScope = keyof typeof BASE_URL;

@Injectable({ providedIn: 'root' })
export class AccountApi {
  private readonly http = inject(HttpClient);

  get(scope: AccountScope): Observable<Account> {
    return this.http.get<Account>(BASE_URL[scope]);
  }

  // Returns a fresh token: changing the password revokes the current one.
  changePassword(scope: AccountScope, change: PasswordChange): Observable<{ token: string }> {
    return this.http.post<{ token: string }>(`${BASE_URL[scope]}/password`, change);
  }
}
