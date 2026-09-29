export interface Message {
  id: string;
  name: string;
  email: string;
  subject: string;
  message: string;
  createdAt: string;
  readAt: string | null;
}

export function isUnread(message: Message): boolean {
  return message.readAt === null;
}

export function replyLink(message: Message): string {
  return `mailto:${message.email}?subject=Re: ${message.subject}`;
}
