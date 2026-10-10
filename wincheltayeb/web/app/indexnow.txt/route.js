import { createHash } from "node:crypto";

export const dynamic = "force-dynamic";

// مفتاح IndexNow: متولد من نفس السر المشترك، ولوحة التحكم بتولّد نفس القيمة
export function GET() {
  const secret = process.env.REVALIDATE_SECRET;
  if (!secret) return new Response("not configured", { status: 404 });
  const key = createHash("sha256").update(`${secret}|indexnow`).digest("hex").slice(0, 32);
  return new Response(key, { headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" } });
}
