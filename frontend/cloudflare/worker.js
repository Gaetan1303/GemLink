/**
 * Asset-aware SPA fallback for GemLink.
 *
 * - Existing static assets are served directly.
 * - Missing files with an extension stay 404 instead of returning index.html
 *   (prevents "MIME text/html" errors for stale Angular chunks).
 * - Browser navigation routes such as /posts/new fall back to index.html.
 */
export default {
  async fetch(request, env) {
    const response = await env.ASSETS.fetch(request);
    if (response.status !== 404 || request.method !== 'GET') {
      return response;
    }

    const url = new URL(request.url);
    const lastSegment = url.pathname.split('/').pop() ?? '';
    const looksLikeAsset = lastSegment.includes('.');

    if (looksLikeAsset) {
      return response;
    }

    const indexUrl = new URL('/index.html', url.origin);
    return env.ASSETS.fetch(new Request(indexUrl, request));
  },
};
