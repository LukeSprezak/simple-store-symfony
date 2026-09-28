import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminAuthService } from '../auth';

@Component({
  selector: 'app-admin-layout',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  host: { class: 'nova' },
  template: `
    <header class="topbar">
      <a class="brand" routerLink="/admin">
        <span class="brand-mark"></span>
        Example Shop <span class="brand-tag">Staff</span>
      </a>
      <div class="user">
        <span class="avatar">{{ auth.email()?.charAt(0)?.toUpperCase() }}</span>
        <span>{{ auth.email() }}</span>
        <button class="nv-btn nv-btn-outline" type="button" (click)="logout()">Log out</button>
      </div>
    </header>
    <div class="body">
      <aside class="sidebar">
        <p class="section">Resources</p>
        <a routerLink="/admin/orders" routerLinkActive="active">Orders</a>
      </aside>
      <main class="content">
        <router-outlet />
        <footer class="footer">Example Shop · Staff panel</footer>
      </main>
    </div>
  `,
  styles: `
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 56px;
      padding: 0 24px;
      background: var(--nv-surface);
      border-bottom: 1px solid var(--nv-border);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--nv-text);
      font-size: 16px;
      font-weight: 800;
      text-decoration: none;
    }

    .brand-mark {
      width: 20px;
      height: 20px;
      border: 4px solid var(--nv-primary);
      border-radius: 50%;
    }

    .brand-tag {
      padding: 1px 8px;
      border-radius: 9999px;
      background: var(--nv-primary);
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .user {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
    }

    .avatar {
      display: grid;
      place-items: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--nv-info-soft);
      color: var(--nv-primary-hover);
      font-weight: 800;
    }

    .body {
      display: flex;
      min-height: calc(100vh - 57px);
    }

    .sidebar {
      width: 224px;
      padding: 24px 16px;
    }

    .section {
      margin: 0 0 8px;
      padding: 0 12px;
      color: var(--nv-muted);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .sidebar a {
      display: block;
      padding: 6px 12px;
      border-radius: 6px;
      color: var(--nv-muted);
      font-weight: 600;
      text-decoration: none;
    }

    .sidebar a:hover {
      color: var(--nv-text);
    }

    .sidebar a.active {
      color: var(--nv-primary);
      font-weight: 800;
    }

    .content {
      display: flex;
      flex: 1;
      flex-direction: column;
      padding: 24px 32px;
    }

    .footer {
      margin-top: auto;
      padding-top: 32px;
      color: var(--nv-muted);
      font-size: 12px;
      text-align: center;
    }
  `,
})
export class AdminLayout {
  protected readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);

  protected logout(): void {
    this.auth.logout();
    this.router.navigateByUrl('/admin/login');
  }
}
