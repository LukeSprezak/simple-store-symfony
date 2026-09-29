import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

interface Category {
  id: string;
  name: string;
  slug: string;
  icon: string | null;
  productCount: number;
  children: Category[];
}

interface Draft {
  id: string | null;
  name: string;
  slug: string;
  parentId: string;
  icon: string;
}

// Must stay in sync with icon_names in index.html, which only ships these glyphs.
const ICONS = ['keyboard', 'desktop_windows', 'headphones', 'storage', 'cable', 'devices', 'sports_esports', 'photo_camera', 'watch', 'home'];

@Component({
  selector: 'app-admin-categories',
  imports: [FormsModule],
  template: `
    <div class="heading">
      <h1>Categories</h1>
      @if (!draft()) {
        <button class="nv-btn" type="button" (click)="create()">
          <span class="material-symbols-rounded" aria-hidden="true">add</span>
          Create category
        </button>
      }
    </div>

    @if (draft(); as draft) {
      <form class="nv-card form" (ngSubmit)="save()">
        <h2>{{ draft.id ? 'Update category' : 'Create category' }}</h2>
        <label class="detail">
          <span>Name</span>
          <input name="name" [ngModel]="draft.name" (ngModelChange)="setName($event)" required maxlength="100" />
        </label>
        <label class="detail">
          <span>Slug</span>
          <input name="slug" [ngModel]="draft.slug" (ngModelChange)="setSlug($event)" required maxlength="100" />
        </label>
        <label class="detail">
          <span>Parent</span>
          <select name="parentId" [ngModel]="draft.parentId" (ngModelChange)="patch({ parentId: $event })">
            <option value="">— Top-level category —</option>
            @for (option of parents(); track option.id) {
              <option [value]="option.id">{{ option.name }}</option>
            }
          </select>
        </label>
        @if (!draft.parentId) {
          <div class="detail">
            <span>Icon</span>
            <div class="icons">
              @for (icon of icons; track icon) {
                <button
                  type="button"
                  class="icon"
                  [class.selected]="draft.icon === icon"
                  [title]="icon"
                  (click)="patch({ icon })"
                >
                  <span class="material-symbols-rounded" aria-hidden="true">{{ icon }}</span>
                </button>
              }
            </div>
          </div>
        }
        <div class="footer">
          @if (error()) {
            <span class="nv-error">{{ error() }}</span>
          }
          <button class="nv-btn nv-btn-outline" type="button" (click)="cancel()">Cancel</button>
          <button class="nv-btn" type="submit" [disabled]="saving()">
            <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
            {{ draft.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </form>
    }

    <div class="nv-card">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th class="right">Products</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @for (category of categories(); track category.id) {
            <tr class="top">
              <td>
                <span class="name">
                  <span class="material-symbols-rounded glyph" aria-hidden="true">{{ category.icon }}</span>
                  {{ category.name }}
                </span>
              </td>
              <td class="muted">{{ category.slug }}</td>
              <td class="right">{{ total(category) }}</td>
              <td class="actions">
                <button type="button" title="Edit" aria-label="Edit" (click)="edit(category, null)">
                  <span class="material-symbols-rounded" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  [title]="category.children.length || category.productCount ? 'Remove its subcategories and products first' : 'Delete'"
                  aria-label="Delete"
                  [disabled]="category.children.length || category.productCount"
                  (click)="remove(category)"
                >
                  <span class="material-symbols-rounded" aria-hidden="true">delete</span>
                </button>
              </td>
            </tr>
            @for (child of category.children; track child.id) {
              <tr>
                <td class="child">{{ child.name }}</td>
                <td class="muted">{{ child.slug }}</td>
                <td class="right">{{ child.productCount }}</td>
                <td class="actions">
                  <button type="button" title="Edit" aria-label="Edit" (click)="edit(child, category.id)">
                    <span class="material-symbols-rounded" aria-hidden="true">edit</span>
                  </button>
                  <button
                    type="button"
                    [title]="child.productCount ? 'Move its products first' : 'Delete'"
                    aria-label="Delete"
                    [disabled]="child.productCount"
                    (click)="remove(child)"
                  >
                    <span class="material-symbols-rounded" aria-hidden="true">delete</span>
                  </button>
                </td>
              </tr>
            }
          } @empty {
            <tr>
              <td class="empty" colspan="4">No categories yet.</td>
            </tr>
          }
        </tbody>
      </table>
    </div>
  `,
  styles: `
    .heading {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
    }

    .form {
      margin-bottom: 32px;
    }

    h2 {
      margin: 0;
      padding: 20px 24px 8px;
      font: 400 20px 'Nunito Sans', sans-serif;
    }

    .detail {
      display: flex;
      align-items: center;
      padding: 14px 24px;
      border-bottom: 1px solid var(--nv-border);
    }

    .detail > span:first-child {
      width: 25%;
      color: var(--nv-muted);
      font-weight: 700;
    }

    input,
    select {
      flex: 1;
      max-width: 400px;
      height: 38px;
      padding: 0 12px;
      border: 1px solid var(--nv-border);
      border-radius: 6px;
      background: var(--nv-header);
      color: var(--nv-text);
      font: inherit;
    }

    input:focus,
    select:focus {
      outline: 2px solid var(--nv-primary);
      outline-offset: 0;
    }

    .icons {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .icon {
      display: grid;
      place-items: center;
      width: 38px;
      height: 38px;
      border: 1px solid var(--nv-border);
      border-radius: 6px;
      background: var(--nv-surface);
      color: var(--nv-muted);
      cursor: pointer;
    }

    .icon.selected {
      border-color: var(--nv-primary);
      background: var(--nv-info-soft);
      color: var(--nv-primary);
    }

    .footer {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      padding: 12px 24px;
      background: var(--nv-header);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      padding: 10px 16px;
      background: var(--nv-header);
      border-bottom: 1px solid var(--nv-border);
      color: var(--nv-muted);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-align: left;
      text-transform: uppercase;
    }

    td {
      padding: 10px 16px;
      border-bottom: 1px solid var(--nv-border);
      vertical-align: middle;
    }

    .top .name {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
    }

    .glyph {
      color: var(--nv-primary);
    }

    .child {
      padding-left: 50px;
    }

    .muted {
      color: var(--nv-muted);
    }

    .right {
      text-align: right;
    }

    .actions {
      width: 96px;
      text-align: right;
      white-space: nowrap;
    }

    .actions button {
      padding: 4px;
      border: 0;
      background: none;
      color: var(--nv-muted);
      cursor: pointer;
    }

    .actions button:hover:not(:disabled) {
      color: var(--nv-primary);
    }

    .actions button:disabled {
      opacity: 0.35;
      cursor: not-allowed;
    }

    .empty {
      padding: 32px;
      color: var(--nv-muted);
      text-align: center;
    }
  `,
})
export class AdminCategories {
  private readonly http = inject(HttpClient);
  protected readonly icons = ICONS;
  protected readonly categories = signal<Category[]>([]);
  protected readonly draft = signal<Draft | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  private slugTouched = false;
  protected readonly parents = computed(() => this.categories().filter((category) => category.id !== this.draft()?.id));

  constructor() {
    this.load();
  }

  // The API counts direct assignments only; a top-level row shows everything filed under it.
  protected total(category: Category): number {
    return category.children.reduce((sum, child) => sum + child.productCount, category.productCount);
  }

  protected create(): void {
    this.slugTouched = false;
    this.error.set('');
    this.draft.set({ id: null, name: '', slug: '', parentId: '', icon: ICONS[0] });
  }

  protected edit(category: Category, parentId: string | null): void {
    this.slugTouched = true;
    this.error.set('');
    this.draft.set({ id: category.id, name: category.name, slug: category.slug, parentId: parentId ?? '', icon: category.icon ?? ICONS[0] });
  }

  protected cancel(): void {
    this.draft.set(null);
  }

  protected patch(changes: Partial<Draft>): void {
    this.draft.update((draft) => (draft ? { ...draft, ...changes } : draft));
  }

  // The slug follows the name until it is edited by hand.
  protected setName(name: string): void {
    this.patch(this.slugTouched ? { name } : { name, slug: this.slugify(name) });
  }

  protected setSlug(slug: string): void {
    this.slugTouched = true;
    this.patch({ slug });
  }

  protected save(): void {
    const draft = this.draft()!;
    const body = { name: draft.name, slug: draft.slug, parentId: draft.parentId || null, icon: draft.parentId ? null : draft.icon };
    this.saving.set(true);
    this.error.set('');
    (draft.id ? this.http.put<void>(`/api/admin/category/${draft.id}`, body) : this.http.post<void>('/api/admin/category', body)).subscribe({
      next: () => {
        this.saving.set(false);
        this.draft.set(null);
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(error.error?.error ?? 'Could not save the category.');
      },
    });
  }

  protected remove(category: Category): void {
    if (!confirm(`Delete the category "${category.name}"?`)) {
      return;
    }

    this.http.delete<void>(`/api/admin/category/${category.id}`).subscribe({
      next: () => this.load(),
      error: (error: HttpErrorResponse) => alert(error.error?.error ?? 'Could not delete the category.'),
    });
  }

  private load(): void {
    this.http.get<{ items: Category[] }>('/api/admin/category').subscribe(({ items }) => this.categories.set(items));
  }

  private slugify(name: string): string {
    return name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
  }
}
