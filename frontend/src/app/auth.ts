import { HttpClient, HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, Observable, throwError } from 'rxjs';

abstract class Session {
  private readonly http = inject(HttpClient);
  readonly token;
  readonly email;

  constructor(
    private readonly storageKey: string,
    private readonly loginUrl: string,
  ) {
    this.token = signal(localStorage.getItem(storageKey));
    this.email = computed<string | undefined>(() => {
      const token = this.token();

      return token ? JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).email : undefined;
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
export class AuthService extends Session {
  constructor() {
    super('token', '/api/login_check');
  }
}

@Injectable({ providedIn: 'root' })
export class AdminAuthService extends Session {
  constructor() {
    super('adminToken', '/api/admin/login_check');
  }
}

// Staff panel and shop keep separate sessions; the request path decides which token is sent.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const admin = req.url.startsWith('/api/admin');
  const session = admin ? inject(AdminAuthService) : inject(AuthService);
  const router = inject(Router);
  const token = session.token();

  return next(token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        session.logout();
        router.navigateByUrl(admin ? '/admin/login' : '/login');
      }

      return throwError(() => error);
    }),
  );
};

export const authGuard: CanActivateFn = () =>
  inject(AuthService).token() ? true : inject(Router).parseUrl('/login');

export const adminGuard: CanActivateFn = () =>
  inject(AdminAuthService).token() ? true : inject(Router).parseUrl('/admin/login');
