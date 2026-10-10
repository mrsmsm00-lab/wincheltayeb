import localFont from "next/font/local";
import "./globals.css";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import CallBar from "@/components/CallBar";
import JsonLd from "@/components/JsonLd";
import { getData } from "@/lib/content";
import { SITE_URL, SITE_NAME } from "@/lib/site";
import { businessSchema, websiteSchema } from "@/lib/schema";

// خطوط مفتوحة المصدر (OFL) مستضافة مع الموقع
const display = localFont({ src: "./fonts/Alexandria-var.woff2", weight: "100 900", variable: "--f-display", display: "swap" });
const body = localFont({
  src: [
    { path: "./fonts/IBMPlexSansArabic-Regular.woff2", weight: "400" },
    { path: "./fonts/IBMPlexSansArabic-Medium.woff2", weight: "500" },
    { path: "./fonts/IBMPlexSansArabic-Bold.woff2", weight: "700" },
  ],
  variable: "--f-body",
  display: "swap",
});

export const metadata = {
  metadataBase: new URL(SITE_URL),
  title: { default: "ونش إنقاذ السويس 24 ساعة | ونش إنقاذ الطيب 01000529729", template: "%s" },
  description: "ونش إنقاذ الطيب: ونش إنقاذ سيارات في محافظة السويس شغال 24 ساعة، ونش ملاكي وسطحة هيدروليك ونقل تقيل وموتوسيكلات وخدمات طريق في السويس والعين السخنة.",
  applicationName: SITE_NAME,
  alternates: { canonical: "/", types: { "application/rss+xml": "/feed.xml" } },
  openGraph: { type: "website", locale: "ar_EG", siteName: SITE_NAME, images: [{ url: "/og.png", width: 1200, height: 630 }] },
  twitter: { card: "summary_large_image" },
  robots: { index: true, follow: true, "max-image-preview": "large", "max-snippet": -1 },
  formatDetection: { telephone: true },
  other: { "geo.region": "EG-SUZ", "geo.placename": "السويس", "geo.position": "29.9668;32.5498", ICBM: "29.9668, 32.5498" },
};

export const viewport = { themeColor: "#0B4EA2", width: "device-width", initialScale: 1 };

export default async function RootLayout({ children }) {
  const { settings, posts } = await getData();
  const services = posts.filter((p) => p.type === "service");
  const areas = posts.filter((p) => p.type === "area");
  return (
    <html lang="ar-EG" dir="rtl" className={`${display.variable} ${body.variable}`}>
      <body>
        <a href="#main" className="skip">انتقل للمحتوى</a>
        <Header settings={settings} />
        <main id="main">{children}</main>
        <Footer settings={settings} services={services} areas={areas} />
        <CallBar settings={settings} />
        <JsonLd data={[businessSchema(settings, areas), websiteSchema()]} />
      </body>
    </html>
  );
}
