/** „HTML simplu” din lecții: păstrăm doar etichetele de text; linkurile se deschid în browserul din aplicație. */
const PERMISE = new Set(['P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'UL', 'OL', 'LI', 'H2', 'H3', 'H4', 'BLOCKQUOTE', 'A', 'SPAN']);

export function curataHtml(html: string): string {
  if (typeof DOMParser === 'undefined') return '';
  const doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
  const curata = (n: Element) => {
    for (const c of Array.from(n.children)) {
      if (!PERMISE.has(c.tagName)) {
        if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM'].includes(c.tagName)) c.remove();
        else {
          curata(c);
          c.replaceWith(...Array.from(c.childNodes));
        }
        continue;
      }
      for (const a of Array.from(c.attributes)) {
        if (!(c.tagName === 'A' && a.name === 'href')) c.removeAttribute(a.name);
      }
      if (c.tagName === 'A') {
        const h = c.getAttribute('href') || '';
        if (!/^https:\/\//i.test(h)) c.removeAttribute('href');
        else {
          c.setAttribute('target', '_blank');
          c.setAttribute('rel', 'noopener');
        }
      }
      curata(c);
    }
  };
  const rad = doc.body.firstElementChild as Element;
  curata(rad);
  return rad.innerHTML;
}
