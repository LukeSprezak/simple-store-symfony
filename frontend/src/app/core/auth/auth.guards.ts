import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AdminSession, ShopSession } from './session';

export const shopGuard: CanActivateFn = () => (inject(ShopSession).token() ? true : inject(Router).parseUrl('/login'));

export const adminGuard: CanActivateFn = () => (inject(AdminSession).token() ? true : inject(Router).parseUrl('/admin/login'));
