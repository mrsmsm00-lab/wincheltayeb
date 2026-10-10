import { abs } from "@/lib/site";

export default function robots() {
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/api/"] }],
    sitemap: [abs("/sitemap.xml"), abs("/video-sitemap.xml")],
    host: abs("/"),
  };
}
