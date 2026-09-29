// "pending_payment" -> "Pending payment"; used for both order statuses and transitions.
export function statusLabel(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1).replaceAll('_', ' ');
}
