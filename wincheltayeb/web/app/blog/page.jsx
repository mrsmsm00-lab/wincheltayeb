import { getPosts } from "@/lib/content";
import PageHead from "@/components/PageHead";
import JsonLd from "@/components/JsonLd";
import { ArticleList } from "@/components/PostList";
import { breadcrumbSchema } from "@/lib/schema";

export const revalidate = 600;
export const metadata = {
  title: "نصايح ومقالات لأصحاب العربيات في السويس | ونش إنقاذ الطيب",
  description: "نصايح عملية باللهجة المصرية: تعمل إيه لو العربية عطلت على طريق السخنة، السطحة ولا الونش، سعر الونش في السويس، والتصرف بعد الحادثة.",
  alternates: { canonical: "/blog" },
};

export default async function Blog() {
  const posts = await getPosts("blog");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "نصايح ومقالات", href: "/blog" }];
  return (
    <>
      <PageHead crumbs={crumbs} title="نصايح ومقالات" lead="حاجات بنشوفها كل يوم على طرق السويس، مكتوبة عشان متقعش فيها." />
      <section className="section"><div className="wrap narrow"><ArticleList posts={posts} /></div></section>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
