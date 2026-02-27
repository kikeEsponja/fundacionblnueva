function setupNavbarLinks() {
    const currentPath = window.location.pathname;
    const isHomePage = currentPath === '/' || currentPath.endsWith('/') || currentPath.endsWith('index.html');

    document.querySelectorAll('button[data-url]').forEach((btn) => {
        let destino = btn.getAttribute('data-url');
        if (!destino) return;

        // Limpiar .html del destino para navegación "bonita"
        const cleanDestino = destino.replace(/\.html$/, '');

        btn.addEventListener('click', () => {
            // Cerrar menú móvil
            const mobileMenu = document.querySelector('.navbar-links.is-open');
            const toggle = document.querySelector('.navbar-toggle.is-open');
            if (mobileMenu) {
                mobileMenu.classList.remove('is-open');
                document.body.style.overflow = '';
            }
            if (toggle) toggle.classList.remove('is-open');

            // Navegación adaptativa:
            // 1. Detectamos si estamos en Live Server (puerto 5500) o entorno local sin Apache
            const isLiveServer = window.location.port === '5500';
            const isLocalFile = window.location.protocol === 'file:';
            const isHtmlEnv = window.location.pathname.endsWith('.html');

            // Si es Live Server o cargado como archivo, forzamos .html.
            // De lo contrario, nos basamos en si la URL actual ya usa .html o no.
            const forceHtml = isLiveServer || isLocalFile || isHtmlEnv;
            function getAppBase() {
                const parts = window.location.pathname.split('/').filter(Boolean);
                if (!parts.length) return '/';
                const first = parts[0];
                if (/\.html$/i.test(first)) return '/';
                return `/${first}/`;
            }
            function resolveDestination(d) {
                // Preservar querystring y hash
                const match = d.match(/^([^?#]*)([?#].*)?$/);
                const pathOnly = match ? match[1] : d;
                const tail = match && match[2] ? match[2] : '';
                // Raíz
                if (pathOnly === '/') {
                    const base = getAppBase();
                    return (forceHtml ? `${base}index.html` : base) + tail;
                }
                // Rutas que terminan en "/": envía a index de esa carpeta en modo HTML
                if (pathOnly.endsWith('/')) {
                    const out = forceHtml ? (pathOnly + 'index.html') : pathOnly;
                    return out + tail;
                }
                if (forceHtml) {
                    const out = pathOnly.endsWith('.html') ? pathOnly : pathOnly + '.html';
                    return out + tail;
                }
                const out = pathOnly.replace(/\.html$/, '');
                return out + tail;
            }
            const finalDestino = resolveDestination(destino);

            window.location.href = finalDestino;
        });

        // Detección de página activa (robusta con o sin .html)
        const normalize = (p) => p.replace(/\.html$/, '').replace(/\/$/, '') || 'index';

        const pathActual = normalize(window.location.pathname).split('/').pop() || 'index';
        const pathDestino = normalize(destino).split('/').pop() || 'index';

        const isActive = pathActual === pathDestino;

        btn.classList.toggle('nav-link-active', isActive);

        if (isActive) {
            btn.style.fontWeight = '700';
            const parentDropdown = btn.closest('.dropdown');
            if (parentDropdown) {
                const toggle = parentDropdown.querySelector('.dropdown-toggle');
                if (toggle) toggle.classList.add('nav-link-active');
            }
        }
    });
}

setupNavbarLinks();

function setupCanonical() {
    const head = document.querySelector('head');
    if (!head) return;
    let link = document.querySelector('link[rel="canonical"]');
    if (!link) {
        link = document.createElement('link');
        link.setAttribute('rel', 'canonical');
        head.appendChild(link);
    }
    const cleanPath = window.location.pathname.replace(/index\.html$/, '').replace(/\.html$/, '');
    const url = window.location.origin + cleanPath;
    link.setAttribute('href', url);
    // Populate og:url with the current canonical URL
    const ogUrl = document.querySelector('meta[property="og:url"]');
    if (ogUrl) ogUrl.setAttribute('content', url);
    // Populate og:image with absolute URL if using a relative path
    const ogImage = document.querySelector('meta[property="og:image"]');
    if (ogImage) {
        const imgContent = ogImage.getAttribute('content') || '';
        if (imgContent && !imgContent.startsWith('http')) {
            const absImg = new URL(imgContent, window.location.href).href;
            ogImage.setAttribute('content', absImg);
        }
    }
}

const postsList = document.getElementById('posts-list');
if (postsList) {
    async function loadPosts() {
        try {
            const res = await fetch('../data/posts.php', { cache: 'no-store' });
            if (!res.ok) return;
            const posts = await res.json();
            postsList.innerHTML = '';
            posts
                .slice()
                .sort((a, b) => {
                    const fa = !!a.featured, fb = !!b.featured;
                    if (fa !== fb) return fa ? -1 : 1;
                    const da = new Date(a.date || 0).getTime();
                    const db = new Date(b.date || 0).getTime();
                    return db - da;
                })
                .forEach(p => {
                    const card = document.createElement('article');
                    card.className = 'post-card';
                    const header = document.createElement('div');
                    header.className = 'post-header';
                    let avatarEl;
                    if (p.adminAvatar) {
                        const img = document.createElement('img');
                        img.className = 'post-avatar';
                        img.alt = 'Administrador';
                        img.src = normalizePath(p.adminAvatar);
                        avatarEl = img;
                    } else {
                        const name = document.createElement('span');
                        name.className = 'post-avatar-name';
                        name.textContent = 'Administrador';
                        avatarEl = name;
                    }
                    const titleWrap = document.createElement('div');
                    titleWrap.className = 'post-title-wrap';
                    const h3 = document.createElement('h3');
                    h3.className = 'post-title';
                    h3.textContent = p.title;
                    const slugEl = document.createElement('div');
                    slugEl.className = 'post-slug';
                    const slugStrong = document.createElement('strong');
                    slugStrong.textContent = '/' + (p.slug || '');
                    slugEl.appendChild(slugStrong);
                    titleWrap.appendChild(h3);
                    titleWrap.appendChild(slugEl);
                    header.appendChild(avatarEl);
                    header.appendChild(titleWrap);
                    const meta = document.createElement('div');
                    meta.className = 'post-meta';
                    const d = new Date(p.date || Date.now());
                    const dateStr = d.toLocaleDateString();
                    const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
                    meta.textContent = dateStr + ' ' + timeStr;
                    const ex = document.createElement('p');
                    ex.className = 'post-excerpt';
                    ex.textContent = p.excerpt || '';
                    const co = document.createElement('div');
                    co.className = 'post-content';
                    co.innerHTML = (p.content || '').replace(/\n/g, '<br>');
                    const reactions = buildReactions(p);
                    card.appendChild(header);
                    card.appendChild(meta);
                    card.appendChild(ex);
                    card.appendChild(co);
                    card.appendChild(reactions);
                    postsList.appendChild(card);
                });
            injectStructuredData(posts);
        } catch { }
    }
    loadPosts();
}
function normalizePath(p) {
    if (!p) return '';
    // Resolve paths relative to the current page using the browser's URL API.
    // This works correctly whether the app is at domain root or a subfolder.
    try {
        return new URL(p, window.location.href).pathname;
    } catch {
        return p;
    }
}
function injectStructuredData(posts) {
    try {
        const head = document.querySelector('head');
        if (!head) return;
        let script = document.getElementById('ld-itemlist');
        if (!script) {
            script = document.createElement('script');
            script.type = 'application/ld+json';
            script.id = 'ld-itemlist';
            head.appendChild(script);
        }
        const cleanPath = window.location.pathname.replace(/index\.html$/, '').replace(/\.html$/, '');
        const baseUrl = window.location.origin + cleanPath;
        const itemList = {
            "@context": "https://schema.org",
            "@type": "ItemList",
            "itemListElement": posts.map((p, idx) => ({
                "@type": "ListItem",
                "position": idx + 1,
                "url": baseUrl + '#' + (p.slug || '')
            }))
        };
        script.textContent = JSON.stringify(itemList);
    } catch { }
}
function buildReactions(post) {
    const wrap = document.createElement('div');
    wrap.className = 'post-reactions';
    const emojisWrap = document.createElement('div');
    emojisWrap.className = 'reaction-emojis';
    const emojis = ['❤️', '😊', '👏'];
    const votedEmoji = getVotedEmoji(post.slug);
    emojis.forEach(e => {
        const btn = document.createElement('button');
        btn.className = 'emoji-btn' + (votedEmoji === e ? ' emoji-active' : '');
        const count = document.createElement('span');
        count.className = 'reaction-count';
        const current = (post.emojis && post.emojis[e]) ? post.emojis[e] : 0;
        count.textContent = String(current);
        btn.textContent = e + ' ';
        btn.appendChild(count);
        btn.addEventListener('click', () => sendReaction(post.slug, 'emoji', e, null, null, count, null, btn));
        emojisWrap.appendChild(btn);
    });
    wrap.appendChild(emojisWrap);
    return wrap;
}
function getVisitorId() {
    try {
        let v = localStorage.getItem('visitor_id');
        if (!v) {
            v = (Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)).slice(0, 32);
            localStorage.setItem('visitor_id', v);
        }
        return v;
    } catch { return ''; }
}
function hasVoted(slug) {
    try { return !!localStorage.getItem('voted_' + slug); } catch { return false; }
}
function getVotedEmoji(slug) {
    try { return localStorage.getItem('voted_' + slug) || ''; } catch { return ''; }
}
function markVoted(slug, emoji) {
    try { localStorage.setItem('voted_' + slug, emoji); } catch { }
}
async function sendReaction(slug, type, value, likeEl, starEl, emojiCountEl, starIconsEl, clickedBtn) {
    try {
        const res = await fetch('../data/react.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ slug, type, value, visitor: getVisitorId() })
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        if (emojiCountEl && data.emojis) {
            const em = value;
            const c = data.emojis[em] || 0;
            emojiCountEl.textContent = String(c);
        }
        if (!data.already) {
            markVoted(slug, value);
            if (clickedBtn) {
                clickedBtn.classList.add('emoji-active');
            }
        }
    } catch { }
}
document.addEventListener('DOMContentLoaded', setupCanonical);

document.querySelectorAll('.navbar-toggle').forEach((toggle) => {
    const navbar = toggle.closest('.navbar');
    if (!navbar) return;
    const links = navbar.querySelector('.navbar-links');
    if (!links) return;

    toggle.addEventListener('click', () => {
        const isOpen = links.classList.toggle('is-open');
        toggle.classList.toggle('is-open', isOpen);
        // Sincronizar estado con el header para el z-index y color
        document.querySelector('header')?.classList.toggle('menu-abierto', isOpen);

        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        // Bloquear scroll del body cuando el menú está abierto
        document.body.style.overflow = isOpen ? 'hidden' : '';

        // Cerrar todos los dropdowns internos al cerrar el menú principal
        if (!isOpen) {
            document.querySelectorAll('.dropdown.is-active').forEach(d => d.classList.remove('is-active'));
        }
    });
});

// --- Lógica del Formulario de Contacto (FormSubmit AJAX) ---
function initContactForm() {
    const form = document.querySelector('#contact-form') || document.querySelector('.contacto-form');
    if (!form) return;

    const statusDiv = document.querySelector('#form-status');
    const submitBtn = document.querySelector('#submit-btn');
    if (!statusDiv || !submitBtn) return;

    // Adaptación para solicitudes de voluntariado vía query param
    try {
        const params = new URLSearchParams(window.location.search);
        const tipo = params.get('tipo');
        if (tipo && tipo.toLowerCase() === 'voluntariado') {
            const subject = form.querySelector('input[name="_subject"]');
            if (subject && !/VOLUNTARIADO/i.test(subject.value || '')) {
                subject.value = `[VOLUNTARIADO] ${subject.value}`;
            }
            const mensaje = form.querySelector('#mensaje');
            if (mensaje && (!mensaje.value || mensaje.value.trim() === '')) {
                mensaje.placeholder = 'Cuéntanos tu disponibilidad, habilidades y motivación para ser voluntario/a.';
            }
            // Agregar campo oculto para identificación interna
            let topic = form.querySelector('input[name="topic"]');
            if (!topic) {
                topic = document.createElement('input');
                topic.type = 'hidden';
                topic.name = 'topic';
                form.appendChild(topic);
            }
            topic.value = 'voluntariado';
        }
    } catch { }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Guardar tiempo de inicio para asegurar visibilidad mínima
        const startTime = Date.now();
        const minDuration = 3000; // 3 segundos mínimo

        // Estado de carga
        const originalBtnText = submitBtn.innerText;
        submitBtn.innerText = 'Enviando...';
        submitBtn.disabled = true;

        statusDiv.classList.remove('success', 'error', 'hide');
        statusDiv.classList.add('info');
        statusDiv.innerText = 'Enviando tu mensaje, por favor espera...';
        statusDiv.style.display = 'flex';
        setTimeout(() => statusDiv.classList.add('show'), 10);

        try {
            const formData = new FormData(form);
            const action = form.getAttribute('action');
            const response = await fetch(action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            // Asegurar visibilidad mínima
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, minDuration - elapsed);
            await new Promise(resolve => setTimeout(resolve, remaining));

            if (response.ok) {
                // Éxito
                statusDiv.classList.remove('info', 'error');
                statusDiv.classList.add('success');
                statusDiv.innerText = '¡Mensaje enviado!';
                form.reset();

                // Ocultarlo suavemente (después de 7 segundos)
                setTimeout(() => {
                    statusDiv.classList.add('hide');
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                        statusDiv.classList.remove('show', 'hide', 'success');
                    }, 400);
                }, 7000);
            } else {
                throw new Error(result.message || 'Error al enviar el formulario');
            }

        } catch (error) {
            // Asegurar visibilidad mínima también en error
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, minDuration - elapsed);
            await new Promise(resolve => setTimeout(resolve, remaining));

            statusDiv.classList.remove('info', 'success');
            statusDiv.classList.add('error');
            statusDiv.innerText = 'Error al enviar.';
            console.error('FormSubmit Error:', error);

            setTimeout(() => {
                statusDiv.classList.add('hide');
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                    statusDiv.classList.remove('show', 'hide', 'error');
                }, 400);
            }, 5000);
        } finally {
            submitBtn.innerText = originalBtnText;
            submitBtn.disabled = false;
        }
    });
}

// Inicializar al cargar
initContactForm();

// --- Lógica de Galería ---
const fotosGrid = document.getElementById('fotos-grid');
const videosGrid = document.getElementById('videos-grid');

if (fotosGrid && videosGrid) {
    function getSiteBase() {
        const parts = window.location.pathname.split('/').filter(Boolean);
        if (parts.length === 0) return '';
        // Known app subdirectories — if parts[0] is one of these, the site
        // lives at the domain root (production). Otherwise it's a subfolder (local XAMPP).
        const KNOWN_DIRS = ['recursos', 'admin', 'assets', 'static'];
        if (KNOWN_DIRS.includes(parts[0].toLowerCase())) return '';
        return '/' + parts[0];
    }
    const SITE_BASE = getSiteBase(); // '' on prod root, '/sistema-fundacion' on local
    function normalizeMediaPath(p) {
        if (!p) return p;
        // Asegurar rutas absolutas desde la raíz del sitio
        const fixed = p
            .replace('../recursos/imagenes/', '../imagenes/')
            .replace('../recursos/videos/', '../videos/');
        // Si empieza con ../, conviértelo a absoluto con base del sitio
        if (fixed.startsWith('../')) {
            // desde /recursos/vistas a /recursos/*
            const abs = fixed.replace(/^\.\.\//, SITE_BASE + '/recursos/');
            // Si quedó /recursos/imagenes -> /sistema-fundacion/recursos/imagenes
            return abs;
        }
        return fixed;
    }
    let fotos = [];
    let videos = [];
    async function loadJSON(url) {
        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error();
            return await res.json();
        } catch {
            return null;
        }
    }
    async function initData() {
        // Build absolute gallery API URLs. If SITE_BASE is empty (root domain),
        // use the app root URL to avoid double-slash or wrong relative paths.
        const appRoot = SITE_BASE || (window.location.origin);
        const imgsUrlPhp = appRoot + '/recursos/data/gallery_images.php';
        const vidsUrlPhp = appRoot + '/recursos/data/gallery_videos.php';
        const imgsUrlJson = appRoot + '/recursos/data/gallery_images.json';
        const vidsUrlJson = appRoot + '/recursos/data/gallery_videos.json';
        let imgs = await loadJSON(imgsUrlPhp);
        let vids = await loadJSON(vidsUrlPhp);
        // Fallback a JSON estático si los endpoints PHP fallan
        if (!Array.isArray(imgs)) imgs = await loadJSON(imgsUrlJson);
        if (!Array.isArray(vids)) vids = await loadJSON(vidsUrlJson);
        if (!Array.isArray(imgs) || !Array.isArray(vids)) {
            console.error('Error cargando datos de galería', {
                imgsTried: [imgsUrlPhp, imgsUrlJson],
                vidsTried: [vidsUrlPhp, vidsUrlJson]
            });
        }
        if (Array.isArray(imgs)) fotos = imgs;
        if (Array.isArray(vids)) videos = vids;
        if (!Array.isArray(imgs) || !Array.isArray(vids)) {
            if (!Array.isArray(imgs)) fotos = [];
            if (!Array.isArray(vids)) videos = [];
        }
        const sorter = (a, b) => {
            const fa = !!a.featured, fb = !!b.featured;
            if (fa !== fb) return fa ? -1 : 1;
            const da = new Date(a.date || 0).getTime();
            const db = new Date(b.date || 0).getTime();
            return db - da;
        };
        fotos.sort(sorter);
        videos.sort(sorter);
        if (fotosGrid) renderFotos(fotos);
        if (videosGrid) renderVideos(videos);
        setupLightboxEvents();
        const fotosSection = document.getElementById('galeria-fotos');
        const videosSection = document.getElementById('galeria-videos');
        const tabs = document.querySelectorAll('.galeria-tab');
        if (fotosSection && videosSection && tabs.length) {
            function setTab(target) {
                const showFotos = target === 'fotos';
                fotosSection.classList.toggle('galeria-seccion-oculta', !showFotos);
                videosSection.classList.toggle('galeria-seccion-oculta', showFotos);
                tabs.forEach(tab => {
                    const t = tab.getAttribute('data-target');
                    tab.classList.toggle('galeria-tab-activa', t === target);
                });
            }
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-target');
                    if (!target) return;
                    setTab(target);
                });
            });
            setTab('fotos');
        }
    }
    const lightboxEl = document.getElementById('lightbox');
    const lightboxBody = document.getElementById('lightbox-body');
    const lightboxClose = document.getElementById('lightbox-close');
    let lightboxTrigger = null;

    function openLightbox(kind, src, alt) {
        lightboxTrigger = document.activeElement || null;
        lightboxBody.innerHTML = '';
        if (kind === 'image') {
            const img = document.createElement('img');
            img.src = src;
            img.alt = alt || '';
            lightboxBody.appendChild(img);
        } else if (kind === 'video') {
            const video = document.createElement('video');
            video.className = 'gallery-video-player';
            video.setAttribute('controls', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('preload', 'metadata');
            const source = document.createElement('source');
            source.src = src;
            source.type = 'video/mp4';
            video.appendChild(source);
            lightboxBody.appendChild(video);
            try { video.play().catch(() => {}); } catch {}
        }
        lightboxEl.classList.add('is-open');
        lightboxEl.setAttribute('aria-hidden', 'false');
        lightboxEl.removeAttribute('inert');
        lightboxClose.focus();
    }

    function closeLightbox() {
        if (lightboxEl.contains(document.activeElement)) {
            try { document.activeElement.blur(); } catch {}
        }
        lightboxEl.classList.remove('is-open');
        lightboxEl.setAttribute('aria-hidden', 'true');
        lightboxEl.setAttribute('inert', '');
        lightboxBody.innerHTML = '';
        if (lightboxTrigger && typeof lightboxTrigger.focus === 'function') {
            lightboxTrigger.focus();
        } else {
            try { document.body.focus(); } catch {}
        }
        lightboxTrigger = null;
    }

    function setupLightboxEvents() {
        lightboxClose.addEventListener('click', closeLightbox);
        lightboxEl.addEventListener('click', (e) => {
            if (e.target === lightboxEl || e.target.classList.contains('lightbox-backdrop')) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && lightboxEl.classList.contains('is-open')) {
                closeLightbox();
            }
        });
    }

    function renderFotos(items) {
        items.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'galeria-item';
            const picture = document.createElement('picture');
            const src = normalizeMediaPath(item.src);
            const thumb = normalizeMediaPath(item.thumb) || src;
            const srcWebp = normalizeMediaPath(item.srcWebp);
            const thumbWebp = normalizeMediaPath(item.thumbWebp) || srcWebp;
            if (thumbWebp || srcWebp) {
                const source = document.createElement('source');
                source.type = 'image/webp';
                source.srcset = (thumbWebp || srcWebp);
                picture.appendChild(source);
            }
            const img = document.createElement('img');
            img.src = thumb;
            img.alt = item.alt || 'Foto';
            img.loading = 'lazy';
            picture.appendChild(img);
            wrapper.appendChild(picture);
            fotosGrid.appendChild(wrapper);
            const bigSrc = srcWebp || src;
            img.addEventListener('click', () => openLightbox('image', bigSrc, item.alt));
        });
    }

    function renderVideos(items) {
        items.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'galeria-item';
            const vsrc = normalizeMediaPath(item.src);
            const video = document.createElement('video');
            video.src = vsrc;
            video.preload = 'metadata';
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            wrapper.appendChild(video);
            videosGrid.appendChild(wrapper);
            wrapper.addEventListener('click', () => openLightbox('video', vsrc));
        });
    }

    initData();
}

// Lógica de Dropdowns en Móvil
document.querySelectorAll('.dropdown-toggle').forEach(btn => {
    btn.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            e.preventDefault();
            e.stopPropagation();
            const parent = btn.closest('.dropdown');
            parent.classList.toggle('is-active');
        }
    });
});
