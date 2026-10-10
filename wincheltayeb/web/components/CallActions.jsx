import Icon from "./Icon";
import { telHref, waHref } from "@/lib/site";

export default function CallActions({ settings, text, compact = false }) {
  return (
    <div className={compact ? "actions actions-compact" : "actions"}>
      <a className="btn btn-call" href={telHref(settings)}>
        <Icon name="phone" size={20} />
        <span>اتصل <bdi dir="ltr">{settings.phone}</bdi></span>
      </a>
      <a className="btn btn-wa" href={waHref(settings, text)} target="_blank" rel="noopener">
        <Icon name="whatsapp" size={20} />
        <span>ابعت لوكيشن واتساب</span>
      </a>
    </div>
  );
}
