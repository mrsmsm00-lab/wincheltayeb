import { postUrl } from "./site";

export function postMetadata(post) {
  if (!post) return { title: "الصفحة مش موجودة", robots: { index: false } };
  const url = postUrl(post);
  return {
    title: post.meta_title || post.title,
    description: post.meta_description || post.excerpt,
    alternates: { canonical: url },
    openGraph: {
      title: post.meta_title || post.title,
      description: post.meta_description || post.excerpt,
      url,
      type: post.type === "blog" ? "article" : "website",
      images: [{ url: post.image || "/og.png" }],
      ...(post.type === "blog" ? { publishedTime: post.published_at, modifiedTime: post.updated_at } : {}),
    },
  };
}
