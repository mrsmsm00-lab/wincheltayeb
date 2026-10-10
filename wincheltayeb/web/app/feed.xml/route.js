import { getPosts } from "@/lib/content";
import { abs, postUrl } from "@/lib/site";

export const revalidate = 600;
const x = (s = "") => String(s).replace(/[<>&'"]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", "'": "&apos;", '"': "&quot;" })[c]);

export async function GET() {
  const posts = await getPosts("blog");
  const items = posts.map((p) => `<item><title>${x(p.title)}</title><link>${abs(postUrl(p))}</link><guid>${abs(postUrl(p))}</guid><pubDate>${new Date(p.published_at).toUTCString()}</pubDate><description>${x(p.excerpt)}</description></item>`).join("");
  const xml = `<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>نصايح ونش إنقاذ الطيب</title><link>${abs("/blog")}</link><description>نصايح لأصحاب العربيات في السويس</description><language>ar-EG</language>${items}</channel></rss>`;
  return new Response(xml, { headers: { "Content-Type": "application/rss+xml; charset=utf-8" } });
}
