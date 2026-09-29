import { CurrencyPipe } from '@angular/common';
import { Component, computed, DestroyRef, effect, ElementRef, inject, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationStart, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs';
import { CentsPipe } from '../../../../shared/ui/cents.pipe';
import { ProductApi } from '../../data-access/product.api';
import { CategoryNode } from '../../domain/category';
import { Product } from '../../domain/product';
import { CategoryStore } from '../../state/category.store';

const FEATURED_COUNT = 4;

@Component({
  selector: 'app-mega-menu',
  imports: [CentsPipe, CurrencyPipe, RouterLink],
  templateUrl: './mega-menu.html',
  styleUrl: './mega-menu.css',
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'open.set(false)',
  },
})
export class MegaMenu {
  private readonly api = inject(ProductApi);
  private readonly host = inject(ElementRef<HTMLElement>);
  protected readonly categories = inject(CategoryStore);
  protected readonly open = signal(false);
  private readonly activeSlug = signal<string | null>(null);
  protected readonly active = computed(() => {
    const tree = this.categories.tree();

    return tree.find((category) => category.slug === this.activeSlug()) ?? tree[0] ?? null;
  });
  protected readonly featured = signal<Record<string, Product[]>>({});

  constructor() {
    inject(Router)
      .events.pipe(
        filter((event) => event instanceof NavigationStart),
        takeUntilDestroyed(inject(DestroyRef)),
      )
      .subscribe(() => this.open.set(false));

    // Featured products are fetched once per category, when it first becomes active in an open menu.
    effect(() => {
      const active = this.active();
      if (this.open() && active) {
        untracked(() => this.loadFeatured(active.slug));
      }
    });
  }

  protected toggle(): void {
    this.categories.load();
    this.open.update((open) => !open);
  }

  protected activate(category: CategoryNode): void {
    this.activeSlug.set(category.slug);
  }

  protected onDocumentClick(event: MouseEvent): void {
    if (!this.host.nativeElement.contains(event.target as Node)) {
      this.open.set(false);
    }
  }

  private loadFeatured(slug: string): void {
    if (this.featured()[slug]) {
      return;
    }

    this.featured.update((featured) => ({ ...featured, [slug]: [] }));
    this.api
      .list(FEATURED_COUNT, null, slug)
      .subscribe(({ items }) => this.featured.update((featured) => ({ ...featured, [slug]: items })));
  }
}
