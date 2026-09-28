import { Routes } from '@angular/router';
import { authGuard } from './auth';
import { Login } from './login';
import { Products } from './products';

export const routes: Routes = [
  { path: 'login', component: Login },
  { path: '', component: Products, canActivate: [authGuard] },
];
