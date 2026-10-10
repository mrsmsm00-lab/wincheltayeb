import { getData } from "@/lib/content";
import { abs } from "@/lib/site";

export const revalidate = 600;
const x = (s = "") => String(s).replace(/[<>&'"]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", "'": "&apos;", '"': "&quot;" })[c]);

export async function GET() {
  const { gallery } = await getData();
  const videos = gallery.filter((g) => g.kind !== "image");
  const items = videos.map((v) => `  <video:video>
    <video:thumbnail_loc>${x(v.thumb || abs("/og.png"))}</video:thumbnail_loc>
    <video:title>${x(v.title)}</video:title>
    <video:description>${x(v.caption || v.title)}</video:description>
    ${v.kind === "youtube" ? `<video:player_loc>https://www.youtube.com/embed/${x(v.youtube_id)}</video:player_loc>` : `<video:content_loc>${x(v.url)}</video:content_loc>`}
    ${v.created_at ? `<video:publication_date>${x(new Date(v.created_at.replace(" ", "T") + "Z").toISOString())}</video:publication_date>` : ""}
  </video:video>`).join("\n");
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
${videos.length ? `<url>\n  <loc>${abs("/gallery")}</loc>\n${items}\n</url>` : ""}
</urlset>`;
  return new Response(xml, { headers: { "Content-Type": "application/xml; charset=utf-8" } });
}
