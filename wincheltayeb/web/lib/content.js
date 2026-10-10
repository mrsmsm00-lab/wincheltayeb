import { cache } from "react";
import seed from "@/content/seed.json";
import siteDefaults from "@/content/site-defaults.json";

const API = process.env.CMS_API_URL;

function parseJson(v, fallback) {
  if (Array.isArray(v) || (v && typeof v === "object")) return v;
  try { return v ? JSON.parse(v) : fallback; } catch { return fallback; }
}

function normalize(data) {
  const settings = { ...seed.settings, ...(data.settings || {}) };
  settings.general_faqs = parseJson(settings.general_faqs, []);
  const sections = parseJson(settings.home_sections, null);
  settings.home_sections = Array.isArray(sections) && sections.length ? sections : siteDefaults.home_sections;
  const menu = parseJson(settings.nav_menu, null);
  settings.nav_menu = (Array.isArray(menu) && menu.length ? menu : siteDefaults.nav_menu).filter((m) => m && m.label && m.href);
  const posts = (data.posts || [])
    .filter((p) => Number(p.published) === 1)
    .map((p) => ({
      ...p,
      faqs: parseJson(p.faqs, []),
      format: p.format === "html" ? "html" : "markdown",
      lat: p.lat === null || p.lat === "" ? null : Number(p.lat),
      lng: p.lng === null || p.lng === "" ? null : Number(p.lng),
      sort: Number(p.sort || 0),
    }))
    .sort((a, b) => a.sort - b.sort);
  return {
    settings,
    districts: data.districts && Object.keys(data.districts).length ? data.districts : seed.districts,
    posts,
    gallery: data.gallery || [],
    landing: (data.landing || []).map((l) => ({ ...l, data: parseJson(l.data, {}) })),
  };
}

// بيجيب المحتوى من لوحة التحكم، ولو مش متاحة بيرجع للمحتوى المدمج في الكود
export const getData = cache(async () => {
  if (API) {
    try {
      const res = await fetch(`${API}?resource=all`, { next: { revalidate: 600 }, signal: AbortSignal.timeout(8000) });
      if (res.ok) {
        const json = await res.json();
        if (Array.isArray(json?.posts) && json.posts.length) return normalize(json);
      }
    } catch (e) {
      console.warn("CMS unreachable, using seed:", e?.message);
    }
  }
  return normalize(seed);
});

export async function getPosts(type) {
  const { posts } = await getData();
  const list = posts.filter((p) => p.type === type);
  if (type === "blog") list.sort((a, b) => String(b.published_at).localeCompare(String(a.published_at)));
  return list;
}

export async function getPost(type, slug) {
  const { posts } = await getData();
  return posts.find((p) => p.type === type && p.slug === decodeURIComponent(slug)) || null;
}

/** صفحة هبوط بالرابط. مع preview token بتجيب المسودة من غير كاش */
export async function getLanding(slug, preview) {
  if (preview && API) {
    try {
      const res = await fetch(`${API}?resource=landing_preview&slug=${encodeURIComponent(slug)}&token=${encodeURIComponent(preview)}`, { cache: "no-store", signal: AbortSignal.timeout(8000) });
      if (res.ok) { const j = await res.json(); if (j.landing) return { ...j.landing, preview: true }; }
    } catch {}
  }
  const { landing } = await getData();
  return landing.find((l) => l.slug === slug) || null;
}
