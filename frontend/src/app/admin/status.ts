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
