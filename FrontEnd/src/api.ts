let token = "";
let csrf = "";
let identityVersion = 0;
let renewal: { version: number; promise: Promise<void> } | null = null;

export function setCredentials(accessToken: string, csrfToken: string) {
  identityVersion++;
  token = accessToken;
  csrf = csrfToken;
  renewal = null;
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
  if (!renewal || renewal.version !== version) {
    const pending = fetch("/api/auth/refresh", {
      method: "POST",
      credentials: "include",
      headers: { "X-CSRF-Token": csrf },
    }).then(async (response) => {
      requireIdentity(version);
      if (!response.ok) throw new ApiError(401, "Please sign in again.");
      const data = (await response.json()).data;
      requireIdentity(version);
      token = data.access_token;
      csrf = data.csrf_token;
    });
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
  if (response.status === 401 && retry && !path.startsWith("auth/")) {
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
  if (response.status === 401) {
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
