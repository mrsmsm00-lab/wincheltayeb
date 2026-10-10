import Link from "next/link";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { getData } from "@/lib/content";

export default async function NotFound() {
  const { settings, posts } = await getData();
  return (
    <>
      <Header settings={settings} />
      <main id="main" className="wrap narrow notfound">
        <h1>الصفحة دي مش موجودة</h1>
        <p>ممكن الرابط اتغير. ارجع للرئيسية أو شوف مناطق التغطية.</p>
        <p><Link href="/" className="btn btn-call">الرئيسية</Link> <Link href="/areas" className="btn btn-ghost">مناطق السويس</Link></p>
      </main>
      <Footer settings={settings} services={posts.filter((p) => p.type === "service")} areas={posts.filter((p) => p.type === "area")} />
    </>
  );
}
