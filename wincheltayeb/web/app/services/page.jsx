import { getData } from "@/lib/content";
import PageHead from "@/components/PageHead";
import JsonLd from "@/components/JsonLd";
import { ServiceRows } from "@/components/PostList";
import { breadcrumbSchema } from "@/lib/schema";

export const revalidate = 600;
export const metadata = {
  title: "خدمات ونش الإنقاذ في السويس | ونش ملاكي ونقل تقيل وموتوسيكلات",
  description: "كل خدمات ونش إنقاذ الطيب في محافظة السويس: ونش ملاكي سطحة، ونش نقل تقيل وميكروباص، ونش موتوسيكلات، خدمات طريق، وإنقاذ حوادث. 24 ساعة.",
  alternates: { canonical: "/services" },
};

export default async function Services() {
  const { posts } = await getData();
  const services = posts.filter((p) => p.type === "service");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "خدماتنا", href: "/services" }];
  return (
    <>
      <PageHead crumbs={crumbs} title="خدمات ونش الإنقاذ في السويس" lead="من البطارية الفاضية لحد العربية المقلوبة على طريق السخنة، اختار الخدمة واعرف بنشتغل فيها إزاي." />
      <section className="section"><div className="wrap"><ServiceRows services={services} /></div></section>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
