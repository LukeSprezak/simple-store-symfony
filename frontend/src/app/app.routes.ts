import { Routes } from '@angular/router';
import { shopRoutes } from './shop/shop.routes';

export const routes: Routes = [
  // The staff panel is a separate bundle, loaded only when someone opens /admin.
  { path: 'admin', loadChildren: () => import('./admin/admin.routes').then((m) => m.adminRoutes) },
  ...shopRoutes,
];
