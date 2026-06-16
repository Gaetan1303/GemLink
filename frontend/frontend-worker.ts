/// <reference types="@cloudflare/workers-types" />

// Point d'entrée Workers + Assets
// Cloudflare sert automatiquement les assets Angular via le binding ASSETS.
// Ce fichier est requis par wrangler mais ne contient aucune logique custom.
export default {
  async fetch(request: Request, env: { ASSETS: Fetcher }): Promise<Response> {
    return env.ASSETS.fetch(request);
  },
};
