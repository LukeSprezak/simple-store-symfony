export function statusLabel(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1).replaceAll('_', ' ');
}

export function statusTone(status: string): string {
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
