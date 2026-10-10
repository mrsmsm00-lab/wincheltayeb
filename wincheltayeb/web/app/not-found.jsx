import Link from "next/link";

export default function NotFound() {
  return (
    <div className="wrap narrow notfound">
      <h1>الصفحة دي مش موجودة</h1>
      <p>ممكن الرابط اتغير. ارجع للرئيسية أو شوف مناطق التغطية.</p>
      <p><Link href="/" className="btn btn-call">الرئيسية</Link> <Link href="/areas" className="btn btn-ghost">مناطق السويس</Link></p>
    </div>
  );
}
