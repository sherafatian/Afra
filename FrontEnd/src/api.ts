let token = "";
let csrf = "";
let identityVersion = 0;
let renewal: { version: number; promise: Promise<void> } | null = null;

const channel =
  typeof window !== "undefined" && typeof BroadcastChannel !== "undefined"
    ? new BroadcastChannel("afra.auth")
    : null;
if (channel)
  channel.onmessage = () => {
    identityVersion++;
    token = "";
    csrf = "";
    renewal = null;
    window.dispatchEvent(new Event("afra-session-changed"));
  };
function claims(): { sub?: string; sid?: string; token_use?: string } | null {
  try {
    return JSON.parse(
      atob(token.split(".")[1].replaceAll("-", "+").replaceAll("_", "/")),
    );
  } catch {
    return null;
  }
}
async function sessionLock<T>(action: () => Promise<T>): Promise<T> {
  if (typeof navigator !== "undefined" && navigator.locks)
    return navigator.locks.request("afra.auth", action);
  return action();
}
async function browserSession(version: number) {
  const expected = claims();
  const response = await fetch("/api/auth/csrf", {
    credentials: "include",
    headers: {
      Accept: "application/json",
      "X-Afra-Client": "spa",
      ...(expected?.sid ? { "X-Afra-Session": expected.sid } : {}),
    },
  });
  requireIdentity(version);
  if (!response.ok)
    throw new ApiError(
      response.status,
      "Browser session changed. Please sign in again.",
    );
  const data = (await response.json()).data;
  requireIdentity(version);
  if (data && expected?.sub && String(data.user_id) !== expected.sub)
    throw new ApiError(401, "Your session changed. Please sign in again.");
  return data as {
    csrf_token: string;
    session_id: string;
    user_id: number;
  } | null;
}
async function rotate(
  version: number,
  initial?: Awaited<ReturnType<typeof browserSession>>,
) {
  const session = initial ?? (await browserSession(version));
  if (!session) throw new ApiError(401, "Please sign in again.");
  const response = await fetch("/api/auth/refresh", {
    method: "POST",
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": session.csrf_token,
    },
    body: JSON.stringify({
      expected_session_id: session.session_id,
      expected_user_id: session.user_id,
    }),
  });
  requireIdentity(version);
  if (!response.ok) throw new ApiError(401, "Please sign in again.");
  const data = (await response.json()).data;
  requireIdentity(version);
  token = data.access_token;
  csrf = data.csrf_token;
  return data.user;
}
export async function bootstrapSession() {
  const version = identityVersion;
  return sessionLock(async () => {
    const session = await browserSession(version);
    if (!session) return null;
    // The browser lock covers both nonce issuance and refresh rotation across tabs.
    return rotate(version, session);
  });
}
export async function endSession() {
  if (claims()?.token_use === "password_change") {
    try {
      await send("auth/logout", {});
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error;
    }
    return;
  }
  const version = identityVersion;
  await sessionLock(async () => {
    const session = await browserSession(version);
    if (!session) return;
    const response = await fetch("/api/auth/end-session", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Afra-Client": "spa",
        "X-CSRF-Token": session.csrf_token,
      },
      body: JSON.stringify({ expected_session_id: session.session_id }),
    });
    requireIdentity(version);
    if (!response.ok)
      throw new ApiError(
        response.status,
        "Could not revoke browser session. Please try again.",
      );
  });
}

export function setCredentials(accessToken: string, csrfToken: string) {
  identityVersion++;
  token = accessToken;
  csrf = csrfToken;
  renewal = null;
  channel?.postMessage({ type: "identity-changed" });
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public fields: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

function requireIdentity(version: number) {
  if (version !== identityVersion)
    throw new ApiError(401, "Your session changed. Please try again.");
}

async function refresh(version: number) {
  requireIdentity(version);
  const identity = claims();
  if (!identity?.sid || !identity.sub)
    throw new ApiError(401, "Please sign in again.");
  if (!renewal || renewal.version !== version) {
    const pending = sessionLock(() => rotate(version)).then(() => undefined);
    const entry = {
      version,
      promise: pending.finally(() => {
        if (renewal === entry) renewal = null;
      }),
    };
    renewal = entry;
  }
  await renewal.promise;
  requireIdentity(version);
}

export async function api<T = any>(
  path: string,
  options: RequestInit = {},
  retry = true,
): Promise<T> {
  const version = identityVersion;
  const response = await fetch("/api/" + path, {
    ...options,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(options.body instanceof FormData
        ? {}
        : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: "Bearer " + token } : {}),
      ...options.headers,
    },
  });
  requireIdentity(version);
  if (response.status === 401 && retry && token && !path.startsWith("auth/")) {
    await refresh(version);
    return api(path, options, false);
  }
  const body = await response.json();
  requireIdentity(version);
  if (!response.ok)
    throw new ApiError(
      response.status,
      body.message || body.error?.message || "Request failed",
      body.errors || {},
    );
  return body.data;
}

export function send<T = any>(
  path: string,
  data: unknown,
  method = "POST",
  version?: number,
) {
  return api<T>(path, {
    method,
    body: JSON.stringify(data),
    headers: version ? { "If-Match": String(version) } : {},
  });
}

export async function download(path: string): Promise<Blob> {
  const version = identityVersion;
  const request = () =>
    fetch("/api/" + path, {
      credentials: "include",
      headers: { Authorization: "Bearer " + token },
    });
  let response = await request();
  requireIdentity(version);
  if (response.status === 401 && token) {
    await refresh(version);
    response = await request();
    requireIdentity(version);
  }
  if (!response.ok) {
    let message = "Download failed";
    try {
      message = (await response.json()).message || message;
    } catch {
      /* An upstream response may not be JSON. */
    }
    throw new ApiError(response.status, message);
  }
  const blob = await response.blob();
  requireIdentity(version);
  return blob;
}
