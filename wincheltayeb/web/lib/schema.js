import { SITE_URL, SITE_NAME, abs, postUrl } from "./site";

const BIZ_ID = `${SITE_URL}/#business`;

export function businessSchema(settings, areas) {
  return {
    "@context": "https://schema.org",
    "@type": ["AutomotiveBusiness", "EmergencyService"],
    "@id": BIZ_ID,
    name: settings.site_name || SITE_NAME,
    alternateName: ["ونش الطيب", "ونش إنقاذ السويس", "Winch El Tayeb"],
    description: "ونش إنقاذ سيارات في محافظة السويس والعين السخنة والجلالة ولحد الزعفرانة، شغال 24 ساعة: ونش ملاكي، نقل تقيل وميكروباص، موتوسيكلات، وخدمات طريق.",
    url: SITE_URL,
    telephone: settings.phone_intl,
    image: abs("/og.png"),
    logo: abs("/logo.svg"),
    priceRange: "$$",
    address: { "@type": "PostalAddress", addressLocality: "السويس", addressRegion: "محافظة السويس", addressCountry: "EG" },
    geo: { "@type": "GeoCoordinates", latitude: 29.9668, longitude: 32.5498 },
    openingHoursSpecification: {
      "@type": "OpeningHoursSpecification",
      dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
      opens: "00:00",
      closes: "23:59",
    },
    areaServed: [
      { "@type": "AdministrativeArea", name: "محافظة السويس" },
      { "@type": "Place", name: "الزعفرانة، محافظة البحر الأحمر" },
      ...areas.map((a) => ({
        "@type": "Place",
        name: a.place_name || a.title,
        url: abs(postUrl(a)),
        ...(a.lat ? { geo: { "@type": "GeoCoordinates", latitude: a.lat, longitude: a.lng } } : {}),
      })),
    ],
    contactPoint: { "@type": "ContactPoint", telephone: settings.phone_intl, contactType: "emergency", areaServed: "EG", availableLanguage: ["ar"] },
    sameAs: [settings.facebook, settings.youtube, settings.tiktok, settings.instagram].filter(Boolean),
  };
}

export function websiteSchema() {
  return { "@context": "https://schema.org", "@type": "WebSite", "@id": `${SITE_URL}/#website`, url: SITE_URL, name: SITE_NAME, inLanguage: "ar-EG", publisher: { "@id": BIZ_ID } };
}

export function breadcrumbSchema(items) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({ "@type": "ListItem", position: i + 1, name: it.name, item: abs(it.href) })),
  };
}

export function faqSchema(faqs) {
  if (!faqs?.length) return null;
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })),
  };
}

export function serviceSchema(post, { areaName } = {}) {
  return {
    "@context": "https://schema.org",
    "@type": "Service",
    name: post.title,
    serviceType: "Vehicle towing and roadside assistance",
    description: post.meta_description || post.excerpt,
    url: abs(postUrl(post)),
    provider: { "@id": BIZ_ID },
    areaServed: areaName
      ? { "@type": "Place", name: areaName, ...(post.lat ? { geo: { "@type": "GeoCoordinates", latitude: post.lat, longitude: post.lng } } : {}), containedInPlace: { "@type": "AdministrativeArea", name: "محافظة السويس" } }
      : { "@type": "AdministrativeArea", name: "محافظة السويس" },
    availableChannel: { "@type": "ServiceChannel", servicePhone: { "@type": "ContactPoint", telephone: "+201000529729" } },
    hoursAvailable: "Mo-Su 00:00-23:59",
  };
}

export function articleSchema(post) {
  return {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    headline: post.title,
    description: post.meta_description || post.excerpt,
    datePublished: post.published_at,
    dateModified: post.updated_at || post.published_at,
    inLanguage: "ar-EG",
    mainEntityOfPage: abs(postUrl(post)),
    image: post.image ? [post.image] : [abs("/og.png")],
    author: { "@type": "Organization", name: SITE_NAME, url: SITE_URL },
    publisher: { "@id": BIZ_ID },
  };
}

export function gallerySchema(items) {
  return {
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    name: "صور وفيديوهات ونش إنقاذ الطيب في السويس",
    url: abs("/gallery"),
    hasPart: items.map((g) =>
      g.kind === "image"
        ? { "@type": "ImageObject", contentUrl: g.url, name: g.title, caption: g.caption || g.title, uploadDate: g.created_at }
        : {
            "@type": "VideoObject",
            name: g.title,
            description: g.caption || g.title,
            thumbnailUrl: g.thumb || abs("/og.png"),
            uploadDate: g.created_at,
            ...(g.kind === "youtube" ? { embedUrl: `https://www.youtube.com/embed/${g.youtube_id}` } : { contentUrl: g.url }),
          }
    ),
  };
}
