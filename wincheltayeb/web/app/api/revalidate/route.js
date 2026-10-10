import { revalidatePath } from "next/cache";

// لوحة التحكم بتنادي هنا بعد أي حفظ، فالموقع والسايت ماب يتحدثوا فوراً
export async function POST(req) {
  const secret = req.headers.get("x-revalidate-secret");
  if (!process.env.REVALIDATE_SECRET || secret !== process.env.REVALIDATE_SECRET) {
    return Response.json({ ok: false, error: "unauthorized" }, { status: 401 });
  }
  revalidatePath("/", "layout");
  return Response.json({ ok: true, at: new Date().toISOString() });
}
