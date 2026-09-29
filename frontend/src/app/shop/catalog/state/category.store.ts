import { inject, Injectable, signal } from '@angular/core';
import { CategoryApi } from '../data-access/category.api';
import { CategoryMatch, CategoryNode, findCategory } from '../domain/category';

@Injectable({ providedIn: 'root' })
export class CategoryStore {
  private readonly api = inject(CategoryApi);
  private requested = false;
  readonly tree = signal<CategoryNode[]>([]);

  // The tree changes rarely, so it is fetched once per session.
  load(): void {
    if (this.requested) {
      return;
    }

    this.requested = true;
    this.api.tree().subscribe({
      next: (tree) => this.tree.set(tree),
      // E.g. a 401 before logging in: allow the next load() to try again.
      error: () => (this.requested = false),
    });
  }

  find(slug: string): CategoryMatch | null {
    return findCategory(this.tree(), slug);
  }
}
