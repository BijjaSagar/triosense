let authenticated = false;

export function markAuthenticated(): void {
  authenticated = true;
}

export function markLoggedOut(): void {
  authenticated = false;
}

export function isAuthenticated(): boolean {
  return authenticated;
}

export function setAuthenticated(value: boolean): void {
  authenticated = value;
}

/** @deprecated Cookie auth — no bearer token stored client-side. */
export function getToken(): string | null {
  return isAuthenticated() ? 'cookie' : null;
}

/** @deprecated Cookie auth — session is HttpOnly. */
export function setToken(_token: string): void {
  markAuthenticated();
}

/** @deprecated Cookie auth — call logout API instead. */
export function clearToken(): void {
  markLoggedOut();
}
