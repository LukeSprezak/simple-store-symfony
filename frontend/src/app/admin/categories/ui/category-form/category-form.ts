import { Component, input, model, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminCategory, CATEGORY_ICONS, CategoryDraft, renameDraft, reslugDraft } from '../../domain/category';

@Component({
  selector: 'app-category-form',
  imports: [FormsModule],
  templateUrl: './category-form.html',
  styleUrl: './category-form.css',
})
export class CategoryForm {
  readonly draft = model.required<CategoryDraft>();
  // Top-level categories the draft may be filed under.
  readonly parents = input.required<AdminCategory[]>();
  readonly saving = input(false);
  readonly error = input('');
  readonly submitted = output();
  readonly cancelled = output();
  protected readonly icons = CATEGORY_ICONS;

  protected rename(name: string): void {
    this.draft.update((draft) => renameDraft(draft, name));
  }

  protected reslug(slug: string): void {
    this.draft.update((draft) => reslugDraft(draft, slug));
  }

  protected patch(changes: Partial<CategoryDraft>): void {
    this.draft.update((draft) => ({ ...draft, ...changes }));
  }
}
