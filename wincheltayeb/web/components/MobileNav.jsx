"use client";
import { useState, useEffect } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import Icon from "./Icon";

export default function MobileNav({ items }) {
  const [open, setOpen] = useState(false);
  const path = usePathname();
  useEffect(() => setOpen(false), [path]);
  useEffect(() => {
    document.body.style.overflow = open ? "hidden" : "";
    const onKey = (e) => e.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);
  return (
    <>
      <button className="menu-btn" aria-expanded={open} aria-controls="mobile-menu" onClick={() => setOpen(!open)}>
        <Icon name={open ? "close" : "menu"} size={26} />
        <span className="sr-only">{open ? "اقفل القائمة" : "افتح القائمة"}</span>
      </button>
      <div id="mobile-menu" className={`mobile-menu${open ? " is-open" : ""}`} hidden={!open}>
        <ul>
          {items.map((n) => (
            <li key={n.href}><Link href={n.href} aria-current={path === n.href ? "page" : undefined}>{n.label}</Link></li>
          ))}
        </ul>
      </div>
    </>
  );
}
