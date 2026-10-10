import { notFound } from "next/navigation";
import { getData, getPost, getPosts } from "@/lib/content";
import { postMetadata } from "@/lib/meta";
import { articleSchema, breadcrumbSchema, faqSchema } from "@/lib/schema";
import PageHead from "@/components/PageHead";
import Markdown from "@/components/Markdown";
import Faq from "@/components/Faq";
import Aside from "@/components/Aside";
import JsonLd from "@/components/JsonLd";
import { formatDate } from "@/components/PostList";

export const revalidate = 600;
export const dynamicParams = true;

export async function generateStaticParams() {
  return (await getPosts("blog")).map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }) {
  const { slug } = await params;
  return postMetadata(await getPost("blog", slug));
}

export default async function BlogPost({ params }) {
  const { slug } = await params;
  const post = await getPost("blog", slug);
  if (!post) notFound();
  const { settings, posts } = await getData();
  const services = posts.filter((p) => p.type === "service");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "نصايح ومقالات", href: "/blog" }, { name: post.title, href: `/blog/${post.slug}` }];
  return (
    <>
      <PageHead crumbs={crumbs} title={post.title} lead={post.excerpt}>
        <p className="meta-line">
          نُشر <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
          {post.updated_at && post.updated_at !== post.published_at && <> ، آخر تحديث <time dateTime={post.updated_at}>{formatDate(post.updated_at)}</time></>}
        </p>
      </PageHead>
      <div className="wrap with-aside">
        <article>
          {post.image && <img className="cover" src={post.image} alt={post.title} />}
          <Markdown source={post.body} format={post.format} />
          <Faq items={post.faqs} />
        </article>
        <Aside settings={settings} links={services} linksTitle="خدماتنا" />
      </div>
      <JsonLd data={[breadcrumbSchema(crumbs), articleSchema(post), faqSchema(post.faqs)]} />
    </>
  );
}
