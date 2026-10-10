import { getData } from "@/lib/content";
import PageHead from "@/components/PageHead";
import JsonLd from "@/components/JsonLd";
import SuezMap from "@/components/SuezMap";
import { AreaPlates } from "@/components/PostList";
import { toMapPoints } from "@/components/mapPoints";
import { breadcrumbSchema } from "@/lib/schema";

export const revalidate = 600;
export const metadata = {
  title: "مناطق تغطية ونش الإنقاذ في محافظة السويس | ونش الطيب",
  description: "ونش إنقاذ الطيب بيغطي كل محافظة السويس: السويس، الأربعين، فيصل، عتاقة، الجناين، بورتوفيق، الأدبية، العين السخنة، جنيفة، والطرق السريعة.",
  alternates: { canonical: "/areas" },
};

export default async function Areas() {
  const { posts, districts } = await getData();
  const areas = posts.filter((p) => p.type === "area");
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "مناطق السويس", href: "/areas" }];
  const groups = Object.entries(districts)
    .map(([key, d]) => ({ key, ...d, items: areas.filter((a) => a.district === key) }))
    .filter((g) => g.items.length);
  return (
    <>
      <PageHead crumbs={crumbs} title="مناطق تغطية ونش الإنقاذ في السويس" lead="بنشتغل في محافظة السويس كلها، من جنيفة في الشمال للجلالة والعين السخنة في الجنوب، ولحد الزعفرانة على طريق البحر. اختار منطقتك." />
      <section className="section">
        <div className="wrap">
          {groups.map((g) => (
            <div key={g.key} className="district-group">
              <h2><span className="dot" style={{ background: g.color }} />{g.name}</h2>
              <AreaPlates areas={g.items} />
            </div>
          ))}
        </div>
      </section>
      <section className="section section-mist">
        <div className="wrap">
          <h2>المناطق على الخريطة</h2>
          <SuezMap points={toMapPoints(areas, districts)} districts={districts} height={480} />
        </div>
      </section>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
