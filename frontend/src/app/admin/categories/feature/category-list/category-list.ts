import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { apiErrorMessage } from '../../../../shared/http/api';
import { AdminCategoryApi } from '../../data-access/admin-category.api';
import { AdminCategory, canDelete, CategoryDraft, draftOf, newDraft, toPayload, totalProducts } from '../../domain/category';
import { CategoryForm } from '../../ui/category-form/category-form';

@Component({
  selector: 'app-admin-category-list',
  imports: [CategoryForm],
  templateUrl: './category-list.html',
  styleUrl: './category-list.css',
})
export class AdminCategoryList {
  private readonly api = inject(AdminCategoryApi);
  protected readonly total = totalProducts;
  protected readonly canDelete = canDelete;
  protected readonly categories = signal<AdminCategory[]>([]);
  protected readonly draft = signal<CategoryDraft | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly parents = computed(() => this.categories().filter((category) => category.id !== this.draft()?.id));

  constructor() {
    this.load();
  }

  protected create(): void {
    this.error.set('');
    this.draft.set(newDraft());
  }

  protected edit(category: AdminCategory, parentId: string | null): void {
    this.error.set('');
    this.draft.set(draftOf(category, parentId));
  }

  protected cancel(): void {
    this.draft.set(null);
  }

  protected save(): void {
    const draft = this.draft()!;
    const request = draft.id ? this.api.update(draft.id, toPayload(draft)) : this.api.create(toPayload(draft));
    this.saving.set(true);
    this.error.set('');
    request.subscribe({
      next: () => {
        this.saving.set(false);
        this.draft.set(null);
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'Could not save the category.'));
      },
    });
  }

  protected remove(category: AdminCategory): void {
    if (!confirm(`Delete the category "${category.name}"?`)) {
      return;
    }

    this.api.remove(category.id).subscribe({
      next: () => this.load(),
      error: (error: HttpErrorResponse) => alert(apiErrorMessage(error, 'Could not delete the category.')),
    });
  }

  private load(): void {
    this.api.tree().subscribe((categories) => this.categories.set(categories));
  }
}
