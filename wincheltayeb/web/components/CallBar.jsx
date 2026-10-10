import Icon from "./Icon";
import { telHref, waHref } from "@/lib/site";

// شريط ثابت في الموبايل
export default function CallBar({ settings }) {
  return (
    <div className="callbar" role="complementary" aria-label="اتصال سريع">
      <a href={telHref(settings)} className="callbar-call"><Icon name="phone" size={20} /> اتصل بالونش</a>
      <a href={waHref(settings)} className="callbar-wa" target="_blank" rel="noopener"><Icon name="whatsapp" size={20} /> واتساب</a>
    </div>
  );
}
