import { getData } from "@/lib/content";
import { telHref, waHref } from "@/lib/site";
import { breadcrumbSchema } from "@/lib/schema";
import PageHead from "@/components/PageHead";
import CallActions from "@/components/CallActions";
import JsonLd from "@/components/JsonLd";

export const revalidate = 600;
export const metadata = {
  title: "كلّم ونش إنقاذ الطيب | 01000529729 اتصال وواتساب",
  description: "رقم ونش إنقاذ الطيب في السويس 01000529729 للاتصال والواتساب، شغالين 24 ساعة في كل محافظة السويس والعين السخنة.",
  alternates: { canonical: "/contact" },
};

export default async function Contact() {
  const { settings } = await getData();
  const crumbs = [{ name: "الرئيسية", href: "/" }, { name: "كلمنا", href: "/contact" }];
  return (
    <>
      <PageHead crumbs={crumbs} title="كلّم ونش الطيب" lead="أسرع طريقة إنك تتصل أو تبعت لوكيشن على الواتساب. بنرد 24 ساعة." />
      <section className="section">
        <div className="wrap narrow contact-block">
          <a className="contact-number" href={telHref(settings)}><bdi dir="ltr">{settings.phone}</bdi></a>
          <p>نفس الرقم للاتصال والواتساب.</p>
          <CallActions settings={settings} />
          <dl className="contact-facts">
            <div><dt>مواعيد الشغل</dt><dd>{settings.hours}</dd></div>
            <div><dt>نطاق الخدمة</dt><dd>محافظة السويس بالكامل، والعين السخنة، والأجزاء التابعة للمحافظة من الطرق السريعة</dd></div>
            <div><dt>العنوان</dt><dd>{settings.address}</dd></div>
            {settings.email && <div><dt>الإيميل</dt><dd><a href={`mailto:${settings.email}`}>{settings.email}</a></dd></div>}
          </dl>
          <h2>عشان نوصلك أسرع، ابعت في الرسالة:</h2>
          <ul className="checklist">
            <li>لوكيشن مكانك من الموبايل</li>
            <li>المكان اللي عايز العربية تروحه</li>
            <li>نوع العربية وموديلها</li>
            <li>العطلة أو شكل الحادثة باختصار</li>
          </ul>
          <p><a className="btn btn-wa" href={waHref(settings, "السلام عليكم، محتاج ونش. مكاني: \nرايح: \nنوع العربية: \nالعطلة: ")} target="_blank" rel="noopener">افتح رسالة واتساب جاهزة</a></p>
        </div>
      </section>
      <JsonLd data={breadcrumbSchema(crumbs)} />
    </>
  );
}
