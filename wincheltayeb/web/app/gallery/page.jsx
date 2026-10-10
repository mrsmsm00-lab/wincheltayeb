import { getData } from "@/lib/content";
import PageHead from "@/components/PageHead";
import GalleryGrid from "@/components/GalleryGrid";
import JsonLd from "@/components/JsonLd";
import { breadcrumbSchema, gallerySchema } from "@/lib/schema";

export const revalidate = 600;
export const metadata = {
  title: "صور وفيديوهات ونش الإنقاذ في السويس | ونش الطيب",
  description: "صور وفيديوهات حقيقية من شغل ونش إنقاذ الطيب في السويس والعين السخنة: سحب عربيات، نقل تقيل، وإنقاذ حوادث.",
  alternates: { canonical: "/gallery" },
};

export default async function Gallery() {
  const { gallery } = await getData();
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "صور وفيديوهات", href: "/gallery" }];
  return (
    <>
      <PageHead crumbs={crumbs} title="صور وفيديوهات من شغلنا" lead="لقطات من مشاوير الونش في أحياء السويس والطرق السريعة." />
      <section className="section"><div className="wrap"><GalleryGrid items={gallery} /></div></section>
      <JsonLd data={[breadcrumbSchema(crumbs), gallery.length ? gallerySchema(gallery) : null]} />
    </>
  );
}
