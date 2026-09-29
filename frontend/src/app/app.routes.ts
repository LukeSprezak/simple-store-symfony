import { Routes } from '@angular/router';
import { AdminLayout } from './admin/layout';
import { AdminLogin } from './admin/login';
import { AdminMessages } from './admin/messages';
import { AdminOrderDetail } from './admin/order';
import { AdminOrders } from './admin/orders';
import { AdminSettings } from './admin/settings';
import { adminGuard, authGuard } from './auth';
import { Cart } from './cart';
import { Contact } from './contact';
import { Login } from './login';
import { Orders } from './orders';
import { ProductPage } from './product';
import { Products } from './products';
import { Settings } from './settings';
import { Shop } from './shop';

export const routes: Routes = [
  { path: 'admin/login', component: AdminLogin },
  {
    path: 'admin',
    component: AdminLayout,
    canActivate: [adminGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'orders' },
      { path: 'orders', component: AdminOrders },
      { path: 'orders/:id', component: AdminOrderDetail },
      { path: 'messages', component: AdminMessages },
      { path: 'settings', component: AdminSettings },
    ],
  },
  {
    path: '',
    component: Shop,
    children: [
      { path: 'login', component: Login },
      { path: 'contact', component: Contact },
      { path: '', component: Products, canActivate: [authGuard] },
      { path: 'products/:id', component: ProductPage, canActivate: [authGuard] },
      { path: 'cart', component: Cart, canActivate: [authGuard] },
      { path: 'orders', component: Orders, canActivate: [authGuard] },
      { path: 'settings', component: Settings, canActivate: [authGuard] },
    ],
  },
];
