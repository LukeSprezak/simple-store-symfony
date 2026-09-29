export interface CartItem {
  id: string;
  productId: string;
  productName: string;
  unitPriceInCents: number;
  quantity: number;
  totalAmountInCents: number;
}

export interface Cart {
  id: string;
  status: string;
  expiresAt: string;
  items: CartItem[];
  totalAmountInCents: number;
}

// Mirrors CartLimits::MAX_QUANTITY_PER_PRODUCT on the backend.
export const MAX_QUANTITY_PER_PRODUCT = 10;

// Expired or converted carts can no longer be changed, so they are dropped client-side.
export function isActive(cart: Cart): boolean {
  return cart.status === 'active';
}

export function countItems(cart: Cart | null): number {
  return cart?.items.reduce((sum, item) => sum + item.quantity, 0) ?? 0;
}

export function quantityInCart(cart: Cart | null, productId: string): number {
  return cart?.items.find((item) => item.productId === productId)?.quantity ?? 0;
}

// How many more units can be added: the per-product cap counts what is already in the cart, and stock caps it too.
export function addableQuantity(inCart: number, stock: number): number {
  return Math.min(MAX_QUANTITY_PER_PRODUCT - inCart, stock);
}
