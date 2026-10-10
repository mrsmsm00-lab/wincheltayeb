"use client";
import { useState, useRef } from "react";
import Icon from "./Icon";

export default function GalleryGrid({ items }) {
  const [filter, setFilter] = useState("all");
  const [current, setCurrent] = useState(null);
  const dlg = useRef(null);
  const list = items.filter((g) => filter === "all" || (filter === "image" ? g.kind === "image" : g.kind !== "image"));

  const open = (g) => { setCurrent(g); dlg.current?.showModal(); };
  const close = () => { dlg.current?.close(); setCurrent(null); };

  if (!items.length) {
    return <p className="empty">لسه مفيش صور أو فيديوهات هنا. أول ما نضيف من لوحة التحكم هتظهر على طول.</p>;
  }
  return (
    <>
      <div className="map-filter" role="group" aria-label="نوع المحتوى">
        {[["all", "الكل"], ["image", "صور"], ["video", "فيديوهات"]].map(([k, l]) => (
          <button key={k} aria-pressed={filter === k} onClick={() => setFilter(k)}>{l}</button>
        ))}
      </div>
      <ul className="gallery">
        {list.map((g) => (
          <li key={g.id}>
            <button onClick={() => open(g)} aria-label={`افتح: ${g.title}`}>
              <img src={g.kind === "image" ? g.url : g.thumb || "/og.png"} alt={g.title} loading="lazy" />
              {g.kind !== "image" && <span className="play"><Icon name="play" size={28} /></span>}
            </button>
            <p>{g.title}</p>
          </li>
        ))}
      </ul>
      <dialog ref={dlg} className="lightbox" onClose={() => setCurrent(null)} onClick={(e) => e.target === dlg.current && close()}>
        {current && (
          <figure>
            {current.kind === "image" && <img src={current.url} alt={current.title} />}
            {current.kind === "video" && <video src={current.url} controls autoPlay playsInline poster={current.thumb || undefined} />}
            {current.kind === "youtube" && (
              <iframe src={`https://www.youtube-nocookie.com/embed/${current.youtube_id}?autoplay=1`} title={current.title} allow="autoplay; encrypted-media; picture-in-picture" allowFullScreen />
            )}
            <figcaption>{current.title}{current.caption ? `: ${current.caption}` : ""}</figcaption>
          </figure>
        )}
        <button className="lightbox-close" onClick={close}><Icon name="close" size={22} /><span className="sr-only">اقفل</span></button>
      </dialog>
    </>
  );
}
