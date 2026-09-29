import { CurrencyPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, computed, DestroyRef, effect, ElementRef, inject, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationStart, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs';
import { CategoryNode, CategoryService } from './categories';

interface FeaturedProduct {
  id: string;
  name: string;
  priceInCents: number;
}

@Component({
  selector: 'app-mega-menu',
  imports: [CurrencyPipe, RouterLink],
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'open.set(false)',
  },
  template: `
    <button class="btn browse" type="button" aria-haspopup="true" [attr.aria-expanded]="open()" (click)="toggle()">
      Browse
      <span class="material-symbols-rounded chevron" [class.up]="open()" aria-hidden="true">expand_more</span>
    </button>
    @if (open()) {
      <div class="panel menu" role="menu">
        <nav class="categories">
          @for (category of categories.tree(); track category.id) {
            <a
              [routerLink]="['/category', category.slug]"
              [class.active]="active()?.id === category.id"
              (mouseenter)="activate(category)"
              (focus)="activate(category)"
            >
              <span class="material-symbols-rounded" aria-hidden="true">{{ category.icon }}</span>
              <span class="name">{{ category.name }}</span>
              <span class="material-symbols-rounded arrow" aria-hidden="true">chevron_right</span>
            </a>
          }
          <hr />
          <a routerLink="/">
            <span class="material-symbols-rounded" aria-hidden="true">storefront</span>
            <span class="name">All products</span>
          </a>
        </nav>
        @if (active(); as category) {
          <div class="subcategories">
            <h4>{{ category.name }}</h4>
            @for (child of category.children; track child.id) {
              <a [routerLink]="['/category', child.slug]">{{ child.name }}</a>
            }
            <a class="all" [routerLink]="['/category', category.slug]">
              See all
              <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
            </a>
          </div>
          <div class="featured">
            <h4>Featured</h4>
            <div class="cards">
              @for (product of featured()[category.slug] ?? []; track product.id) {
                <a class="product" [routerLink]="['/products', product.id]">
                  <strong>{{ product.name }}</strong>
                  <span>{{ product.priceInCents / 100 | currency }}</span>
                </a>
              }
            </div>
          </div>
        }
      </div>
    }
  `,
  styles: `
    :host {
      position: relative;
    }

    .browse {
      height: 40px;
      padding: 0 12px 0 16px;
    }

    .chevron {
      transition: transform 0.15s;
    }

    .chevron.up {
      transform: rotate(180deg);
    }

    .menu {
      position: absolute;
      top: calc(100% + 12px);
      left: 0;
      z-index: 20;
      display: grid;
      grid-template-columns: 240px 200px 400px;
      padding: 0;
      box-shadow: 4px 4px 0 0 var(--text);
      animation: drop 0.15s ease-out;
    }

    @keyframes drop {
      from {
        opacity: 0;
        transform: translateY(-6px);
      }
    }

    .categories {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 12px;
      border-right: 1px solid var(--border);
    }

    .categories a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: var(--radius);
      color: var(--text);
      font-weight: 600;
      text-decoration: none;
    }

    .categories a.active {
      background: var(--primary-soft);
      color: var(--primary);
    }

    .name {
      flex: 1;
    }

    .arrow {
      color: var(--muted);
      font-size: 18px;
    }

    hr {
      width: 100%;
      margin: 8px 0;
      border: 0;
      border-top: 1px solid var(--border);
    }

    .subcategories,
    .featured {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 20px;
    }

    h4 {
      margin: 0 0 12px;
      color: var(--muted);
      font: 600 11px 'Open Sans', sans-serif;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .subcategories a {
      padding: 6px 0;
      color: var(--text);
      text-decoration: none;
    }

    .subcategories a:hover {
      color: var(--primary);
    }

    .subcategories .all {
      display: flex;
      align-items: center;
      gap: 4px;
      margin-top: 8px;
      color: var(--primary);
      font-weight: 600;
    }

    .all .material-symbols-rounded {
      font-size: 16px;
    }

    .featured {
      background: var(--bg);
      border-radius: 0 10px 10px 0;
    }

    .cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .product {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 8px;
      min-height: 88px;
      padding: 14px;
      border: 1px solid var(--text);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      background: var(--surface);
      color: var(--text);
      text-decoration: none;
    }

    .product span {
      color: var(--primary);
      font: 700 16px Ubuntu, sans-serif;
    }

    .product:hover {
      background: var(--primary-soft);
    }
  `,
})
export class MegaMenu {
  private readonly http = inject(HttpClient);
  private readonly host = inject(ElementRef<HTMLElement>);
  protected readonly categories = inject(CategoryService);
  protected readonly open = signal(false);
  private readonly activeSlug = signal<string | null>(null);
  protected readonly active = computed(() => {
    const tree = this.categories.tree();

    return tree.find((category) => category.slug === this.activeSlug()) ?? tree[0] ?? null;
  });
  protected readonly featured = signal<Record<string, FeaturedProduct[]>>({});

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

  private loadFeatured(slug: string): void {
    if (this.featured()[slug]) {
      return;
    }

    this.featured.update((featured) => ({ ...featured, [slug]: [] }));
    this.http
      .get<{ items: FeaturedProduct[] }>('/api/product', { params: { limit: 4, category: slug } })
      .subscribe(({ items }) => this.featured.update((featured) => ({ ...featured, [slug]: items })));
  }

  protected onDocumentClick(event: MouseEvent): void {
    if (!this.host.nativeElement.contains(event.target as Node)) {
      this.open.set(false);
    }
  }
}
