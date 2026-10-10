const paths = {
  car: "M5 11l1.6-4.2A2 2 0 0 1 8.5 5.5h7a2 2 0 0 1 1.9 1.3L19 11m-14 0h14m-14 0a2 2 0 0 0-2 2v3h2m14-5a2 2 0 0 1 2 2v3h-2M5 16v2m14-2v2M7.5 14h.01M16.5 14h.01",
  truck: "M2 6h11v9H2zM13 9h4l3 3v3h-7M5.5 18.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z",
  bike: "M5.5 17.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm13 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM5.5 14.5l4-6h5l4 6M14 8.5l-2-3h-2",
  tools: "M14.7 6.3a4 4 0 0 0-5.4 5.2L3 17.8 6.2 21l6.3-6.3a4 4 0 0 0 5.2-5.4l-2.6 2.6-2.4-.6-.6-2.4z",
  alert: "M12 3l9.5 17h-19zM12 10v4m0 3h.01",
  gear: "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8-3l2-1-1-3-2.2.2-1.4-1.4L17.6 5 15 3.8 13.7 5.6h-2L10.3 3.8 7.7 5l.2 2.8L6.5 9.2 4.3 9l-1 3 2 1v1l-2 1 1 3 2.2-.2 1.4 1.4-.2 2.8 2.6 1.2 1.4-1.8h2l1.4 1.8 2.6-1.2-.2-2.8 1.4-1.4 2.2.2 1-3-2-1z",
  phone: "M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2",
  pin: "M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z",
  clock: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-13v4l3 2",
  menu: "M4 7h16M4 12h16M4 17h16",
  close: "M6 6l12 12M18 6L6 18",
  play: "M8 5v14l11-7z",
};

export default function Icon({ name, size = 24, className, title }) {
  if (name === "whatsapp") {
    return (
      <svg width={size} height={size} viewBox="0 0 24 24" className={className} aria-hidden={title ? undefined : true} role={title ? "img" : undefined}>
        {title && <title>{title}</title>}
        <path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4a.5.5 0 0 0 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.5-.3z" />
      </svg>
    );
  }
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden={title ? undefined : true} role={title ? "img" : undefined}>
      {title && <title>{title}</title>}
      <path d={paths[name] || paths.car} />
    </svg>
  );
}
