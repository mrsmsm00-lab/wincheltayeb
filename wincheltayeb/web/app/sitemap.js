import { getData } from "@/lib/content";
import { abs, postUrl } from "@/lib/site";

// بيتحدث تلقائي مع كل تعديل في لوحة التحكم (ISR + revalidate)
export const revalidate = 600;

export default async function sitemap() {
  const { posts, gallery } = await getData();
  const latest = posts.reduce((m, p) => (String(p.updated_at) > m ? String(p.updated_at) : m), "2026-05-01");
  const statics = [
    ["/", 1.0, "daily"],
    ["/services", 0.9, "weekly"],
    ["/areas", 0.9, "weekly"],
    ["/suez-map", 0.8, "weekly"],
    ["/blog", 0.7, "weekly"],
    ["/gallery", 0.6, "weekly"],
    ["/contact", 0.7, "monthly"],
  ].map(([path, priority, changeFrequency]) => ({ url: abs(path), lastModified: latest, changeFrequency, priority }));
  const prio = { service: 0.9, area: 0.85, blog: 0.6, page: 0.5 };
  const dynamic = posts.map((p) => ({
    url: abs(postUrl(p)),
    lastModified: p.updated_at || p.published_at,
    changeFrequency: p.type === "blog" ? "monthly" : "weekly",
    priority: prio[p.type] ?? 0.5,
    ...(p.image ? { images: [p.image] } : {}),
  }));
  const imgs = gallery.filter((g) => g.kind === "image").map((g) => g.url);
  const galleryEntry = statics.find((s) => s.url === abs("/gallery"));
  if (galleryEntry && imgs.length) galleryEntry.images = imgs.slice(0, 1000);
  const { landing } = await getData();
  const lps = (landing || []).filter((l) => !l.noindex).map((l) => ({ url: abs(`/${l.slug}`), lastModified: l.updated_at, changeFrequency: "monthly", priority: 0.5 }));
  return [...statics, ...dynamic, ...lps];
}
