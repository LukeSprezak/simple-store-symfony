export interface JwtClaims {
  email: string;
  roles: string[];
}

// Reads the claims for display only; the API verifies the signature on every request.
export function decodeJwt(token: string): JwtClaims {
  return JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
}
