import { postUrl } from "@/lib/site";

export function toMapPoints(areas, districts) {
  return areas
    .filter((a) => a.lat && a.lng)
    .map((a) => ({
      slug: a.slug,
      name: a.place_name || a.title.replace(/^ونش إنقاذ\s*/, ""),
      lat: a.lat,
      lng: a.lng,
      eta: a.eta,
      district: a.district,
      districtName: districts[a.district]?.name || "",
      color: districts[a.district]?.color || "#0B4EA2",
      url: postUrl(a),
    }));
}
