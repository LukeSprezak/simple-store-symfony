export interface AdminOrder {
  id: string;
  status: string;
  customerEmail: string;
  createdAt: string;
  totalAmountInCents: number;
  // Transitions the backend allows from the current status.
  transitions: string[];
}

export interface StatusChange {
  eventId: string;
  transition: string;
  fromStatus: string;
  toStatus: string;
  recordedAt: string;
}

export type StatusTone = 'success' | 'danger' | 'warning' | '';

export function orderNumber(order: AdminOrder): string {
  return order.id.slice(-8);
}

export function statusTone(status: string): StatusTone {
  switch (status) {
    case 'delivered':
    case 'returned':
      return 'success';
    case 'cancelled':
    case 'failed':
      return 'danger';
    case 'pending_payment':
    case 'return_requested':
    case 'awaiting_receipt':
      return 'warning';
    default:
      return '';
  }
}

export function isDestructive(transition: string): boolean {
  return transition === 'cancel' || transition === 'fail_payment';
}

const TRANSITION_ICONS: Record<string, string> = {
  pay: 'payments',
  confirm_payment: 'task_alt',
  fail_payment: 'error',
  process_order: 'inventory_2',
  mark_ready_for_shipment: 'package_2',
  ship: 'local_shipping',
  deliver: 'home',
  cancel: 'cancel',
  request_return: 'undo',
  retrieved: 'assignment_return',
  complete_return: 'done_all',
};

export function transitionIcon(transition: string): string {
  return TRANSITION_ICONS[transition];
}
