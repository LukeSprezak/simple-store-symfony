import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';

export interface CategoryNode {
  id: string;
  name: string;
  slug: string;
  icon: string | null;
  children: CategoryNode[];
}

@Injectable({ providedIn: 'root' })
export class CategoryService {
  private readonly http = inject(HttpClient);
  private requested = false;
  readonly tree = signal<CategoryNode[]>([]);

  // The tree changes rarely, so it is fetched once per session.
  load(): void {
    if (this.requested) {
      return;
    }

    this.requested = true;
    this.http.get<{ items: CategoryNode[] }>('/api/category').subscribe(({ items }) => this.tree.set(items));
  }

  find(slug: string): { category: CategoryNode; parent: CategoryNode | null } | null {
    for (const category of this.tree()) {
      if (category.slug === slug) {
        return { category, parent: null };
      }

      const child = category.children.find((node) => node.slug === slug);
      if (child) {
        return { category: child, parent: category };
      }
    }

    return null;
  }
}
