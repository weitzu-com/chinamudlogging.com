// Temporary certificate validation only. Main website requests never enter here.
export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (!['chinamudlogging.com', 'www.chinamudlogging.com'].includes(url.hostname) ||
        !/^\/\.well-known\/acme-challenge\/[A-Za-z0-9_-]+$/.test(url.pathname)) {
      return new Response('Not found', { status: 404 });
    }
    if (!['GET', 'HEAD'].includes(request.method)) return new Response('Method not allowed', { status: 405 });
    try {
      const result = await fetch(`https://preview.chinamudlogging.com${url.pathname}`, {
        method: request.method, redirect: 'manual', cf: { cacheTtl: 0 },
      });
      if (result.status !== 200) return new Response('Challenge not found', { status: 404 });
      return new Response(request.method === 'HEAD' ? null : result.body, {
        status: 200, headers: { 'Content-Type': 'text/plain', 'Cache-Control': 'no-store' },
      });
    } catch { return new Response('Validation origin unavailable', { status: 502 }); }
  },
};
