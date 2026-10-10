export default function Faq({ items, title = "أسئلة بتتسأل كتير" }) {
  if (!items?.length) return null;
  return (
    <section className="faq" aria-labelledby="faq-title">
      <h2 id="faq-title">{title}</h2>
      {items.map((f, i) => (
        <details key={i} open={i === 0}>
          <summary>{f.q}</summary>
          <p>{f.a}</p>
        </details>
      ))}
    </section>
  );
}
