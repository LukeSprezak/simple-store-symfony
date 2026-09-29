export interface AdminCategory {
  id: string;
  name: string;
  slug: string;
  icon: string | null;
  // Products assigned directly to this category, not to its subcategories.
  productCount: number;
  children: AdminCategory[];
}

export interface CategoryDraft {
  id: string | null;
  name: string;
  slug: string;
  parentId: string;
  icon: string;
  // Until the slug is edited by hand it follows the name.
  slugTouched: boolean;
}

export interface CategoryPayload {
  name: string;
  slug: string;
  parentId: string | null;
  icon: string | null;
}

// Must stay in sync with icon_names in index.html, which only ships these glyphs.
export const CATEGORY_ICONS = ['keyboard', 'desktop_windows', 'headphones', 'storage', 'cable', 'devices', 'sports_esports', 'photo_camera', 'watch', 'home'];

export function slugify(name: string): string {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

// A top-level row shows everything filed under it.
export function totalProducts(category: AdminCategory): number {
  return category.children.reduce((sum, child) => sum + child.productCount, category.productCount);
}

// The backend refuses to delete a category that still has subcategories or products.
export function canDelete(category: AdminCategory): boolean {
  return category.children.length === 0 && category.productCount === 0;
}

export function newDraft(): CategoryDraft {
  return { id: null, name: '', slug: '', parentId: '', icon: CATEGORY_ICONS[0], slugTouched: false };
}

export function draftOf(category: AdminCategory, parentId: string | null): CategoryDraft {
  return { id: category.id, name: category.name, slug: category.slug, parentId: parentId ?? '', icon: category.icon ?? CATEGORY_ICONS[0], slugTouched: true };
}

export function renameDraft(draft: CategoryDraft, name: string): CategoryDraft {
  return draft.slugTouched ? { ...draft, name } : { ...draft, name, slug: slugify(name) };
}

export function reslugDraft(draft: CategoryDraft, slug: string): CategoryDraft {
  return { ...draft, slug, slugTouched: true };
}

// Only top-level categories carry an icon.
export function toPayload(draft: CategoryDraft): CategoryPayload {
  return { name: draft.name, slug: draft.slug, parentId: draft.parentId || null, icon: draft.parentId ? null : draft.icon };
}
