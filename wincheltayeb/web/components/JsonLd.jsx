export default function JsonLd({ data }) {
  const list = (Array.isArray(data) ? data : [data]).filter(Boolean);
  return list.map((d, i) => (
    <script key={i} type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(d).replace(/</g, "\\u003c") }} />
  ));
}
