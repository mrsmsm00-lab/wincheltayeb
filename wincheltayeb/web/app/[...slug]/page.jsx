import { notFound } from "next/navigation";
import "../lp.css";
import { getData, getLanding, getPost } from "@/lib/content";
import { postMetadata } from "@/lib/meta";
import { breadcrumbSchema, faqSchema } from "@/lib/schema";
import LandingPage, { landingTitle, blocksOf } from "@/components/landing/LandingPage";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import CallBar from "@/components/CallBar";
import PageHead from "@/components/PageHead";
import Markdown from "@/components/Markdown";
import Aside from "@/components/Aside";
import JsonLd from "@/components/JsonLd";

// أي رابط على مستوى الدومين: صفحة هبوط (wincheltayeb.com/ads) أو صفحة عادية من اللوحة
export const revalidate = 600;

const pathOf = (slug) => (slug || []).map((s) => decodeURIComponent(s)).join("/");
const kw = (sp) => {
  const raw = String(sp?.keyword || sp?.utm_term || sp?.kw || "").replace(/[^\p{L}\p{N}\s\-]/gu, " ").replace(/\s+/g, " ").trim();
  return raw.length >= 2 && raw.length <= 60 ? raw : "";
};

export async function generateMetadata({ params, searchParams }) {
  const { slug } = await params;
  const sp = await searchParams;
  const path = pathOf(slug);
  const lp = await getLanding(path, sp?.preview);
  if (lp) {
    const { settings } = await getData();
    const title = landingTitle(lp, settings, kw(sp));
    return {
      title: lp.data.meta_title || title,
      description: lp.data.meta_description || blocksOf(lp.data).find((b) => b.type === "hero")?.subheadline || lp.data.subheadline,
      alternates: { canonical: `/${lp.slug}` },
      robots: lp.noindex || lp.preview ? { index: false, follow: false } : { index: true, follow: true },
      openGraph: { title: lp.data.meta_title || title, url: `/${lp.slug}`, images: [{ url: lp.data.og_image || blocksOf(lp.data).find((b) => b.type === "hero")?.image || lp.data.hero_image || settings.og_image || "/og.png" }] },
    };
  }
  if (slug?.length === 1) return postMetadata(await getPost("page", path));
  return { title: "الصفحة مش موجودة", robots: { index: false } };
}

export default async function CatchAll({ params, searchParams }) {
  const { slug } = await params;
  const sp = await searchParams;
  const path = pathOf(slug);
  const { settings, posts } = await getData();

  const lp = await getLanding(path, sp?.preview);
  if (lp) return <LandingPage lp={lp} settings={settings} keyword={kw(sp)} />;

  const page = slug?.length === 1 ? await getPost("page", path) : null;
  if (!page) notFound();
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: page.title, href: `/${page.slug}` }];
  return (
    <>
      <Header settings={settings} />
      <main id="main">
        <PageHead crumbs={crumbs} title={page.title} lead={page.excerpt} />
        <div className="wrap with-aside">
          <article><Markdown source={page.body} format={page.format} /></article>
          <Aside settings={settings} />
        </div>
      </main>
      <Footer settings={settings} services={posts.filter((p) => p.type === "service")} areas={posts.filter((p) => p.type === "area")} />
      <CallBar settings={settings} />
      <JsonLd data={[breadcrumbSchema(crumbs), faqSchema(page.faqs)]} />
    </>
  );
}
