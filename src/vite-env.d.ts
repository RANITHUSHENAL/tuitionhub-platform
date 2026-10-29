/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Laravel API origin, e.g. http://localhost:8000. Defaults to that value in development. */
  readonly VITE_API_BASE_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
