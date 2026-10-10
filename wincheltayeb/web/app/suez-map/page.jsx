import { getData } from "@/lib/content";
import PageHead from "@/components/PageHead";
import SuezMap from "@/components/SuezMap";
import JsonLd from "@/components/JsonLd";
import { toMapPoints } from "@/components/mapPoints";
import { breadcrumbSchema } from "@/lib/schema";

export const revalidate = 600;
export const metadata = {
  title: "خريطة تغطية ونش الإنقاذ في محافظة السويس | ونش الطيب",
  description: "خريطة تفاعلية لكل المناطق اللي ونش إنقاذ الطيب بيغطيها في محافظة السويس، وكل نقطة بتوديك لصفحة المنطقة ووقت الوصول.",
  alternates: { canonical: "/suez-map" },
};

export default async function MapPage() {
  const { posts, districts } = await getData();
  const areas = posts.filter((p) => p.type === "area");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "خريطة التغطية", href: "/suez-map" }];
  return (
    <>
      <PageHead crumbs={crumbs} title="خريطة تغطية ونش الإنقاذ في السويس" lead="فلتر حسب الحي، ودوس على أي منطقة تشوف وقت الوصول وتفتح صفحتها." />
      <section className="section">
        <div className="wrap">
          <SuezMap points={toMapPoints(areas, districts)} districts={districts} height={640} />
          <p className="map-note">الخريطة من OpenStreetMap. الأماكن تقريبية لتوضيح التغطية، ووقت الوصول بيتأكد في التليفون.</p>
        </div>
      </section>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
