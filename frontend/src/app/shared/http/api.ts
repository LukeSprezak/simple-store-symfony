import { HttpErrorResponse } from '@angular/common/http';

export interface Page<T> {
  items: T[];
  nextCursor: string | null;
}

// Query params for the API's cursor pagination; empty filters are left out.
export function pageParams(limit: number, after: string | null, filters: Record<string, string | undefined> = {}): Record<string, string | number> {
  const params: Record<string, string | number> = { limit };
  for (const [key, value] of Object.entries({ after: after ?? undefined, ...filters })) {
    if (value) {
      params[key] = value;
    }
  }

  return params;
}

export function apiErrorMessage(error: HttpErrorResponse, fallback: string): string {
  return error.error?.error ?? fallback;
}
