import { notFound } from "next/navigation";
import { getData, getPost, getPosts } from "@/lib/content";
import { postMetadata } from "@/lib/meta";
import { breadcrumbSchema, faqSchema, serviceSchema } from "@/lib/schema";
import PageHead from "@/components/PageHead";
import Markdown from "@/components/Markdown";
import Faq from "@/components/Faq";
import Aside from "@/components/Aside";
import JsonLd from "@/components/JsonLd";
import SuezMap from "@/components/SuezMap";
import { toMapPoints } from "@/components/mapPoints";

export const revalidate = 600;
export const dynamicParams = true;

export async function generateStaticParams() {
  return (await getPosts("area")).map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }) {
  const { slug } = await params;
  return postMetadata(await getPost("area", slug));
}

const dist = (a, b) => Math.hypot((a.lat || 0) - (b.lat || 0), (a.lng || 0) - (b.lng || 0));

export default async function AreaPage({ params }) {
  const { slug } = await params;
  const post = await getPost("area", slug);
  if (!post) notFound();
  const { settings, posts, districts } = await getData();
  const areas = posts.filter((p) => p.type === "area");
  const nearby = areas.filter((a) => a.slug !== post.slug && a.lat).sort((a, b) => dist(a, post) - dist(b, post)).slice(0, 6);
  const place = post.place_name || post.title.replace(/^ونش إنقاذ\s*/, "");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "مناطق السويس", href: "/areas" }, { name: place, href: `/areas/${post.slug}` }];
  return (
    <>
      <PageHead crumbs={crumbs} title={post.title} lead={post.excerpt}>
        {post.eta && <p className="eta-chip">وقت الوصول المتوقع: {post.eta}</p>}
      </PageHead>
      <div className="wrap with-aside">
        <article>
          <Markdown source={post.body} format={post.format} />
          {post.lat && (
            <section className="inline-section">
              <h2>{place} على خريطة السويس</h2>
              <SuezMap points={toMapPoints(areas, districts)} districts={districts} focus={post.slug} showList={false} height={380} />
            </section>
          )}
          <Faq items={post.faqs} />
        </article>
        <Aside settings={settings} title={`محتاج ونش في ${place}؟`} links={nearby} linksTitle="مناطق قريبة" />
      </div>
      <JsonLd data={[breadcrumbSchema(crumbs), serviceSchema(post, { areaName: place }), faqSchema(post.faqs)]} />
    </>
  );
}
