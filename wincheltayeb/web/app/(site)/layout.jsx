import Header from "@/components/Header";
import Footer from "@/components/Footer";
import CallBar from "@/components/CallBar";
import GadsConversions from "@/components/GadsConversions";
import { getData } from "@/lib/content";

// شكل الموقع الرئيسي: القايمة فوق والفوتر وشريط الاتصال
export default async function SiteLayout({ children }) {
  const { settings, posts } = await getData();
  return (
    <>
      <Header settings={settings} />
      <main id="main">{children}</main>
      <Footer settings={settings} services={posts.filter((p) => p.type === "service")} areas={posts.filter((p) => p.type === "area")} />
      <CallBar settings={settings} />
      {settings.gads_site_wide === "1" && settings.gads_id && <GadsConversions id={settings.gads_id} callLabel={settings.gads_call_label} waLabel={settings.gads_wa_label} />}
    </>
  );
}
