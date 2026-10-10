"use client";
import { useEffect, useRef, useState } from "react";
import "leaflet/dist/leaflet.css";

const esc = (s = "") => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

// خريطة محافظة السويس: كل نقطة بتودّي لمقال المنطقة
// مبنية على Leaflet + OpenStreetMap (مفتوحين المصدر)
export default function SuezMap({ points, districts, focus = null, showList = true, height = 560 }) {
  const el = useRef(null);
  const mapRef = useRef(null);
  const markers = useRef({});
  const [active, setActive] = useState(focus);
  const [filter, setFilter] = useState("all");

  useEffect(() => {
    let map;
    let cancelled = false;
    import("leaflet").then(({ default: L }) => {
      if (cancelled || !el.current) return;
      map = L.map(el.current, { scrollWheelZoom: false, tap: true });
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(map);
      const bounds = [];
      points.forEach((p) => {
        const icon = L.divIcon({
          className: "plate-marker",
          html: `<span style="--c:${esc(p.color)}"><i></i></span>`,
          iconSize: [30, 30],
          iconAnchor: [15, 15],
          popupAnchor: [0, -14],
        });
        const m = L.marker([p.lat, p.lng], { icon, title: p.name, alt: p.name, keyboard: true, riseOnHover: true }).addTo(map);
        m.bindPopup(
          `<div class="map-pop"><strong>${esc(p.name)}</strong><span>${esc(p.districtName)}</span>${p.eta ? `<span>الوصول: ${esc(p.eta)}</span>` : ""}<a href="${esc(p.url)}">ونش ${esc(p.name)}</a></div>`
        );
        m.bindTooltip(esc(p.name), { direction: "top", offset: [0, -14] });
        m.on("click", () => setActive(p.slug));
        markers.current[p.slug] = { marker: m, district: p.district };
        bounds.push([p.lat, p.lng]);
      });
      if (focus && markers.current[focus]) {
        map.setView(markers.current[focus].marker.getLatLng(), 11);
        markers.current[focus].marker.openPopup();
      } else if (bounds.length) {
        map.fitBounds(bounds, { padding: [24, 24] });
      }
      mapRef.current = map;
    });
    return () => {
      cancelled = true;
      if (map) map.remove();
      markers.current = {};
    };
  }, [points, focus]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;
    Object.values(markers.current).forEach(({ marker, district }) => {
      const show = filter === "all" || district === filter;
      if (show && !map.hasLayer(marker)) marker.addTo(map);
      if (!show && map.hasLayer(marker)) marker.remove();
    });
  }, [filter]);

  const goTo = (slug) => {
    const map = mapRef.current;
    const entry = markers.current[slug];
    if (!map || !entry) return;
    setActive(slug);
    map.flyTo(entry.marker.getLatLng(), 12, { duration: 0.6 });
    entry.marker.openPopup();
  };

  const visible = points.filter((p) => filter === "all" || p.district === filter);
  const usedDistricts = [...new Set(points.map((p) => p.district))];

  return (
    <div className={showList ? "suez-map has-list" : "suez-map"} style={{ "--map-h": `${height}px` }}>
      <div className="suez-map-canvas" ref={el} role="region" aria-label="خريطة مناطق التغطية في محافظة السويس" />
      {showList && (
        <div className="suez-map-side">
          <div className="map-filter" role="group" aria-label="فلترة حسب الحي">
            <button aria-pressed={filter === "all"} onClick={() => setFilter("all")}>الكل</button>
            {usedDistricts.map((d) => (
              <button key={d} aria-pressed={filter === d} onClick={() => setFilter(d)}>
                <span className="dot" style={{ background: districts[d]?.color }} />
                {districts[d]?.name || d}
              </button>
            ))}
          </div>
          <ul className="map-list">
            {visible.map((p) => (
              <li key={p.slug} className={active === p.slug ? "is-active" : undefined}>
                <button onClick={() => goTo(p.slug)} aria-label={`اعرض ${p.name} على الخريطة`}>
                  <span className="dot" style={{ background: p.color }} />
                  {p.name}
                </button>
                <a href={p.url}>التفاصيل</a>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
