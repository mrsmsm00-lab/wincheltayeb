import { notFound } from "next/navigation";
import { getData, getPost } from "@/lib/content";
import { postMetadata } from "@/lib/meta";
import { breadcrumbSchema } from "@/lib/schema";
import PageHead from "@/components/PageHead";
import Markdown from "@/components/Markdown";
import Aside from "@/components/Aside";
import JsonLd from "@/components/JsonLd";

export const revalidate = 600;
export async function generateMetadata() { return postMetadata(await getPost("page", "about")); }

export default async function About() {
  const post = await getPost("page", "about");
  if (!post) notFound();
  const { settings } = await getData();
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: post.title, href: "/about" }];
  return (
    <>
      <PageHead crumbs={crumbs} title={post.title} lead={post.excerpt} />
      <div className="wrap with-aside">
        <article><Markdown source={post.body} /></article>
        <Aside settings={settings} />
      </div>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
