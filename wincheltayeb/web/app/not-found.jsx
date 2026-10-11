// صفحة خفيفة: الصفحة دي بتتبعت مع كل صفحة في الموقع، فلازم تفضل صغيرة
export default function NotFound() {
  return (
    <main id="main" className="wrap narrow notfound">
      <p><a href="/"><img src="/logo.svg" alt="ونش إنقاذ الطيب" width="56" height="56" /></a></p>
      <h1>الصفحة دي مش موجودة</h1>
      <p>ممكن الرابط اتغير. ارجع للرئيسية أو شوف مناطق التغطية.</p>
      <p><a href="/" className="btn btn-call">الرئيسية</a> <a href="/areas" className="btn btn-ghost">مناطق السويس</a> <a href="tel:+201000529729" className="btn btn-ghost">اتصل 01000529729</a></p>
    </main>
  );
}
