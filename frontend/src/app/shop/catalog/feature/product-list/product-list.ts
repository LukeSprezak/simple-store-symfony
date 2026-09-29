import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, ElementRef, inject, input, signal, untracked, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { apiErrorMessage } from '../../../../shared/http/api';
import { Spinner } from '../../../../shared/ui/spinner/spinner';
import { CartStore } from '../../../cart';
import { ProductApi } from '../../data-access/product.api';
import { Product } from '../../domain/product';
import { CategoryStore } from '../../state/category.store';
import { ProductCard } from '../../ui/product-card/product-card';

const PAGE_SIZE = 12;

@Component({
  selector: 'app-product-list',
  imports: [ProductCard, RouterLink, Spinner],
  templateUrl: './product-list.html',
  styleUrl: './product-list.css',
  host: {
    '(window:scroll)': 'onScroll()',
    '(window:wheel)': 'onScroll($event)',
    '(window:touchmove)': 'onScroll()',
  },
})
export class ProductList {
  // Category slug from the /category/:slug route; the home page has none.
  readonly slug = input<string>();
  private readonly api = inject(ProductApi);
  private readonly cart = inject(CartStore);
  private readonly categories = inject(CategoryStore);
  private readonly sentinel = viewChild.required<ElementRef<HTMLElement>>('sentinel');
  protected readonly current = computed(() => {
    const slug = this.slug();

    return slug ? this.categories.find(slug) : null;
  });
  protected readonly products = signal<Product[]>([]);
  protected readonly nextCursor = signal<string | null>(null);
  protected readonly loading = signal(false);
  protected readonly adding = signal<string | null>(null);
  protected readonly error = signal('');
  private request?: Subscription;

  constructor() {
    this.categories.load();

    // The same component instance is reused when navigating between categories.
    effect(() => {
      this.slug();
      untracked(() => {
        this.request?.unsubscribe();
        this.products.set([]);
        this.nextCursor.set(null);
        this.loading.set(false);
        this.load();
      });
    });
  }

  // Loads only on user scroll intent (not on visibility), so the first 3 rows stay alone even when they don't fill the screen.
  protected onScroll(event?: WheelEvent): void {
    if (event && event.deltaY <= 0) {
      return;
    }

    if (this.nextCursor() && this.sentinel().nativeElement.getBoundingClientRect().top < innerHeight + 200) {
      this.load();
    }
  }

  protected add(product: Product): void {
    this.adding.set(product.id);
    this.error.set('');
    this.cart.add(product.id, 1).subscribe({
      next: () => this.adding.set(null),
      error: (error: HttpErrorResponse) => {
        this.adding.set(null);
        this.error.set(apiErrorMessage(error, `Could not add ${product.name} to the cart.`));
      },
    });
  }

  private load(): void {
    if (this.loading()) {
      return;
    }

    this.loading.set(true);
    this.request = this.api.list(PAGE_SIZE, this.nextCursor(), this.slug()).subscribe((page) => {
      this.products.update((products) => [...products, ...page.items]);
      this.nextCursor.set(page.nextCursor);
      this.loading.set(false);
    });
  }
}
