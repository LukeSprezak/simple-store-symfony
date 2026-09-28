import { Routes } from '@angular/router';
import { authGuard } from './auth';
import { Cart } from './cart';
import { Login } from './login';
import { Products } from './products';

export const routes: Routes = [
  { path: 'login', component: Login },
  { path: '', component: Products, canActivate: [authGuard] },
  { path: 'cart', component: Cart, canActivate: [authGuard] },
];
