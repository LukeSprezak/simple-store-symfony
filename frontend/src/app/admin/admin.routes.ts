import { Routes } from '@angular/router';
import { adminGuard } from '../core/auth';
import { AdminLoginPage, AdminSettingsPage } from './account';
import { AdminCategoryList } from './categories';
import { AdminMessageList } from './messages';
import { AdminOrderDetails, AdminOrderList } from './orders';
import { AdminLayout } from './shell/admin-layout/admin-layout';

export const adminRoutes: Routes = [
  { path: 'login', component: AdminLoginPage },
  {
    path: '',
    component: AdminLayout,
    canActivate: [adminGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'orders' },
      { path: 'orders', component: AdminOrderList },
      { path: 'orders/:id', component: AdminOrderDetails },
      { path: 'categories', component: AdminCategoryList },
      { path: 'messages', component: AdminMessageList },
      { path: 'settings', component: AdminSettingsPage },
    ],
  },
];
