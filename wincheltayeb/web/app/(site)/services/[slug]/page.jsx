import { notFound } from "next/navigation";
import { getData, getPost, getPosts } from "@/lib/content";
import { postMetadata } from "@/lib/meta";
import { breadcrumbSchema, faqSchema, serviceSchema } from "@/lib/schema";
import PageHead from "@/components/PageHead";
import Markdown from "@/components/Markdown";
import Faq from "@/components/Faq";
import Aside from "@/components/Aside";
import JsonLd from "@/components/JsonLd";
import { AreaPlates } from "@/components/PostList";

export const revalidate = 600;
export const dynamicParams = true;

export async function generateStaticParams() {
  return (await getPosts("service")).map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }) {
  const { slug } = await params;
  return postMetadata(await getPost("service", slug));
}

export default async function ServicePage({ params }) {
  const { slug } = await params;
  const post = await getPost("service", slug);
  if (!post) notFound();
  const { settings, posts } = await getData();
  const others = posts.filter((p) => p.type === "service" && p.slug !== post.slug);
  const areas = posts.filter((p) => p.type === "area");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "خدماتنا", href: "/services" }, { name: post.title, href: `/services/${post.slug}` }];
  return (
    <>
      <PageHead crumbs={crumbs} title={post.title} lead={post.excerpt} />
      <div className="wrap with-aside">
        <article>
          <Markdown source={post.body} format={post.format} />
          <section className="inline-section">
            <h2>{post.title} في كل مناطق السويس</h2>
            <AreaPlates areas={areas} />
          </section>
          <Faq items={post.faqs} />
        </article>
        <Aside settings={settings} links={others} linksTitle="خدمات تانية" />
      </div>
      <JsonLd data={[breadcrumbSchema(crumbs), serviceSchema(post), faqSchema(post.faqs)]} />
    </>
  );
}
