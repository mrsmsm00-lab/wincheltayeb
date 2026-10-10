export default function manifest() {
  return {
    name: "ونش إنقاذ الطيب",
    short_name: "ونش الطيب",
    description: "ونش إنقاذ السويس 24 ساعة",
    start_url: "/",
    display: "standalone",
    dir: "rtl",
    lang: "ar-EG",
    background_color: "#ffffff",
    theme_color: "#0B4EA2",
    icons: [{ src: "/logo.svg", sizes: "any", type: "image/svg+xml" }],
  };
}
