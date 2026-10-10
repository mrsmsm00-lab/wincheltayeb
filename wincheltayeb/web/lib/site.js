export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL || "https://wincheltayeb.com").replace(/\/$/, "");
export const SITE_NAME = "ونش إنقاذ الطيب";
export const abs = (path = "/") => (path.startsWith("http") ? path : `${SITE_URL}${path.startsWith("/") ? "" : "/"}${path}`);
export const telHref = (s) => `tel:${s.phone_intl || "+2" + s.phone}`;
export const waHref = (s, text = "السلام عليكم، محتاج ونش إنقاذ في السويس") =>
  `https://wa.me/${s.whatsapp}?text=${encodeURIComponent(text)}`;
export const typePath = { service: "services", area: "areas", blog: "blog", page: "" };
export const postUrl = (p) => (p.type === "page" ? `/${p.slug}` : `/${typePath[p.type]}/${p.slug}`);
