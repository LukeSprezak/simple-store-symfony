import { Routes } from '@angular/router';
import { shopGuard } from '../core/auth';
import { LoginPage, SettingsPage } from './account';
import { CartPage } from './cart';
import { ProductDetails, ProductList } from './catalog';
import { ContactPage } from './contact';
import { OrderList } from './orders';
import { ShopLayout } from './shell/shop-layout/shop-layout';

export const shopRoutes: Routes = [
  {
    path: '',
    component: ShopLayout,
    children: [
      { path: 'login', component: LoginPage },
      { path: 'contact', component: ContactPage },
      { path: '', component: ProductList, canActivate: [shopGuard] },
      { path: 'category/:slug', component: ProductList, canActivate: [shopGuard] },
      { path: 'products/:id', component: ProductDetails, canActivate: [shopGuard] },
      { path: 'cart', component: CartPage, canActivate: [shopGuard] },
      { path: 'orders', component: OrderList, canActivate: [shopGuard] },
      { path: 'settings', component: SettingsPage, canActivate: [shopGuard] },
    ],
  },
];
