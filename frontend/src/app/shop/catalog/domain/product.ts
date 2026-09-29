export interface Product {
  id: string;
  name: string;
  description: string;
  priceInCents: number;
  stockQuantity: number;
}

export function isInStock(product: Product): boolean {
  return product.stockQuantity > 0;
}

// Short, human-friendly code shown to customers instead of the full UUID.
export function productCode(product: Product): string {
  return product.id.slice(-8).toUpperCase();
}
