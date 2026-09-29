import { Component, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { timer } from 'rxjs';
import { AdminSession } from '../../../core/auth';
import { UnreadMessagesStore } from '../../messages';

const UNREAD_REFRESH_MS = 60_000;

@Component({
  selector: 'app-admin-layout',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './admin-layout.html',
  styleUrl: './admin-layout.css',
  host: { class: 'nova' },
})
export class AdminLayout {
  protected readonly session = inject(AdminSession);
  protected readonly unread = inject(UnreadMessagesStore);
  private readonly router = inject(Router);

  constructor() {
    // Picks up messages sent by customers while the panel is open.
    timer(0, UNREAD_REFRESH_MS)
      .pipe(takeUntilDestroyed())
      .subscribe(() => this.unread.refresh());
  }

  protected logout(): void {
    this.session.logout();
    this.router.navigateByUrl('/admin/login');
  }
}
