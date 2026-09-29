import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AdminSession, ShopSession } from './session';

// Staff panel and shop keep separate sessions; the request path decides which token is sent.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const admin = req.url.startsWith('/api/admin');
  const session = admin ? inject(AdminSession) : inject(ShopSession);
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
