import { HttpClient, HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, Observable, throwError } from 'rxjs';

const TOKEN_KEY = 'token';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  readonly token = signal(localStorage.getItem(TOKEN_KEY));
  // UI hint only; the API enforces the role on every request.
  readonly isAdmin = computed(() => {
    const token = this.token();
    if (!token) {
      return false;
    }

    const roles: string[] = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).roles;

    return roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN');
  });

  login(email: string, password: string): Observable<void> {
    return this.http.post<{ token: string }>('/api/login_check', { email, password }).pipe(
      map(({ token }) => {
        localStorage.setItem(TOKEN_KEY, token);
        this.token.set(token);
      }),
    );
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    this.token.set(null);
  }
}

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  return next(token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        auth.logout();
        router.navigateByUrl('/login');
      }

      return throwError(() => error);
    }),
  );
};

export const authGuard: CanActivateFn = () =>
  inject(AuthService).token() ? true : inject(Router).parseUrl('/login');
