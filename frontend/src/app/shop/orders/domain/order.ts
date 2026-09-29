export interface Order {
  id: string;
  status: string;
  createdAt: string;
  totalAmountInCents: number;
}

export interface StatusChange {
  eventId: string;
  transition: string;
  fromStatus: string;
  toStatus: string;
  recordedAt: string;
}

// Short order number shown to customers instead of the full UUID.
export function orderNumber(order: Order): string {
  return order.id.slice(-8);
}
