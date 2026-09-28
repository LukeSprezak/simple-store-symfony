import { Routes } from '@angular/router';
import { authGuard } from './auth';
import { Home } from './home';
import { Login } from './login';

export const routes: Routes = [
  { path: 'login', component: Login },
  { path: '', component: Home, canActivate: [authGuard] },
];
