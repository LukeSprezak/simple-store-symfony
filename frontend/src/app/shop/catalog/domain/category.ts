export interface CategoryNode {
  id: string;
  name: string;
  slug: string;
  icon: string | null;
  children: CategoryNode[];
}

export interface CategoryMatch {
  category: CategoryNode;
  parent: CategoryNode | null;
}

// The tree is two levels deep: top-level categories and their subcategories.
export function findCategory(tree: CategoryNode[], slug: string): CategoryMatch | null {
  for (const category of tree) {
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
