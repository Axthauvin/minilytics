/**
 * Minilytics Lucide Icons Helper
 * Provides crisp SVG icons powered by Lucide with zero-dependency built-in fallbacks.
 */

const Icons = {
    // Built-in SVG paths matching Lucide definitions
    svgs: {
        'user': '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'user-check': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'zap': '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'activity': '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.48 12H2"/>',
        'globe': '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'monitor': '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
        'smartphone': '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
        'tablet': '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><line x1="12" x2="12.01" y1="18" y2="18"/>',
        'laptop': '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/>',
        'clock': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'tag': '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'compass': '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'external-link': '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'chevron-right': '<path d="m9 18 6-6-6-6"/>',
        'chevron-down': '<path d="m6 9 6 6 6-6"/>',
        'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'terminal': '<polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/>',
        'cpu': '<rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/>',
        'mouse-pointer-click': '<path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/><path d="m16.071 7.929 1.414-1.414"/><path d="M19 13h2"/><path d="M14 4V2"/><path d="M9 13H7"/><path d="m7.929 7.929-1.414-1.414"/>'
    },

    get(name, { size = 16, strokeWidth = 2, className = '', color = 'currentColor' } = {}) {
        // If official Lucide library is present
        if (window.lucide && window.lucide.icons && window.lucide.icons[name]) {
            try {
                return window.lucide.icons[name].toSvg({
                    width: size,
                    height: size,
                    'stroke-width': strokeWidth,
                    class: className,
                    color: color
                });
            } catch (e) {
                // fallback
            }
        }

        // SVG fallback
        const inner = this.svgs[name] || this.svgs['activity'] || '<circle cx="12" cy="12" r="9"/>';
        return `
            <svg 
                xmlns="http://www.w3.org/2000/svg" 
                width="${size}" 
                height="${size}" 
                viewBox="0 0 24 24" 
                fill="none" 
                stroke="${color}" 
                stroke-width="${strokeWidth}" 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                class="lucide lucide-${name} ${className}"
            >
                ${inner}
            </svg>
        `;
    },

    // Compact deterministic MD5 algorithm for Gravatar hashing
    md5(string) {
        function rotateLeft(lValue, iShiftBits) {
            return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
        }
        function addUnsigned(lX, lY) {
            var lX4, lY4, lX8, lY8, lResult;
            lX8 = (lX & 0x80000000);
            lY8 = (lY & 0x80000000);
            lX4 = (lX & 0x40000000);
            lY4 = (lY & 0x40000000);
            lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
            if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
            if (lX4 | lY4) {
                if (lResult & 0x40000000) return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
                else return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
            } else {
                return (lResult ^ lX8 ^ lY8);
            }
        }
        function F(x, y, z) { return (x & y) | ((~x) & z); }
        function G(x, y, z) { return (x & z) | (y & (~z)); }
        function H(x, y, z) { return (x ^ y ^ z); }
        function I(x, y, z) { return (y ^ (x | (~z))); }
        function FF(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(F(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function GG(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(G(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function HH(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(H(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function II(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(I(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function convertToWordArray(string) {
            var lWordCount;
            var lMessageLength = string.length;
            var lNumberOfWords_temp1 = lMessageLength + 8;
            var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
            var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
            var lWordArray = Array(lNumberOfWords - 1);
            var lBytePosition = 0;
            var lByteCount = 0;
            while (lByteCount < lMessageLength) {
                lWordCount = (lByteCount - (lByteCount % 4)) / 4;
                lBytePosition = (lByteCount % 4) * 8;
                lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount) << lBytePosition));
                lByteCount++;
            }
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
            lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
            lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
            return lWordArray;
        }
        function wordToHex(lValue) {
            var WordToHexValue = '', WordToHexValue_temp = '', lByte, lCount;
            for (lCount = 0; lCount <= 3; lCount++) {
                lByte = (lValue >>> (lCount * 8)) & 255;
                WordToHexValue_temp = '0' + lByte.toString(16);
                WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
            }
            return WordToHexValue;
        }
        var x = convertToWordArray(string);
        var k, AA, BB, CC, DD, a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
        var S11=7, S12=12, S13=17, S14=22;
        var S21=5, S22=9 , S23=14, S24=20;
        var S31=4, S32=11, S33=16, S34=23;
        var S41=6, S42=10, S43=15, S44=21;
        for (k = 0; k < x.length; k += 16) {
            AA = a; BB = b; CC = c; DD = d;
            a = FF(a, b, c, d, x[k+0],  S11, 0xD76AA478);
            d = FF(d, a, b, c, x[k+1],  S12, 0xE8C7B756);
            c = FF(c, d, a, b, x[k+2],  S13, 0x242070DB);
            b = FF(b, c, d, a, x[k+3],  S14, 0xC1BDCEEE);
            a = FF(a, b, c, d, x[k+4],  S11, 0xF57C0FAF);
            d = FF(d, a, b, c, x[k+5],  S12, 0x4787C62A);
            c = FF(c, d, a, b, x[k+6],  S13, 0xA8304613);
            b = FF(b, c, d, a, x[k+7],  S14, 0xFD469501);
            a = FF(a, b, c, d, x[k+8],  S11, 0x698098D8);
            d = FF(d, a, b, c, x[k+9],  S12, 0x8B44F7AF);
            c = FF(c, d, a, b, x[k+10], S13, 0xFFFF5BB1);
            b = FF(b, c, d, a, x[k+11], S14, 0x895CD7BE);
            a = FF(a, b, c, d, x[k+12], S11, 0x6B901122);
            d = FF(d, a, b, c, x[k+13], S12, 0xFD987193);
            c = FF(c, d, a, b, x[k+14], S13, 0xA679438E);
            b = FF(b, c, d, a, x[k+15], S14, 0x49B40821);
            a = GG(a, b, c, d, x[k+1],  S21, 0xF61E2562);
            d = GG(d, a, b, c, x[k+6],  S22, 0xC040B340);
            c = GG(c, d, a, b, x[k+11], S23, 0x265E5A51);
            b = GG(b, c, d, a, x[k+0],  S24, 0xE9B6C7AA);
            a = GG(a, b, c, d, x[k+5],  S21, 0xD62F105D);
            d = GG(d, a, b, c, x[k+10], S22, 0x2441453);
            c = GG(c, d, a, b, x[k+15], S23, 0xD8A1E681);
            b = GG(b, c, d, a, x[k+4],  S24, 0xE7D3FBC8);
            a = GG(a, b, c, d, x[k+9],  S21, 0x21E1CDE6);
            d = GG(d, a, b, c, x[k+14], S22, 0xC33707D6);
            c = GG(c, d, a, b, x[k+3],  S23, 0xF4D50D87);
            b = GG(b, c, d, a, x[k+8],  S24, 0x455A14ED);
            a = GG(a, b, c, d, x[k+13], S21, 0xA9E3E905);
            d = GG(d, a, b, c, x[k+2],  S22, 0xFCEFA3F8);
            c = GG(c, d, a, b, x[k+7],  S23, 0x676F02D9);
            b = GG(b, c, d, a, x[k+12], S24, 0x8D2A4C8A);
            a = HH(a, b, c, d, x[k+5],  S31, 0xFFFA3942);
            d = HH(d, a, b, c, x[k+8],  S32, 0x8771F681);
            c = HH(c, d, a, b, x[k+11], S33, 0x6D9D6122);
            b = HH(b, c, d, a, x[k+14], S34, 0xFDE5380C);
            a = HH(a, b, c, d, x[k+1],  S31, 0xA4BEEA44);
            d = HH(d, a, b, c, x[k+4],  S32, 0x4BDECFA9);
            c = HH(c, d, a, b, x[k+7],  S33, 0xF6BB4B60);
            b = HH(b, c, d, a, x[k+10], S34, 0xBEBFBC70);
            a = HH(a, b, c, d, x[k+13], S31, 0x289B7EC6);
            d = HH(d, a, b, c, x[k+0],  S32, 0xEAA127FA);
            c = HH(c, d, a, b, x[k+3],  S33, 0xD4EF3085);
            b = HH(b, c, d, a, x[k+6],  S34, 0x4881D05);
            a = HH(a, b, c, d, x[k+9],  S31, 0xD9D4D039);
            d = HH(d, a, b, c, x[k+12], S32, 0xE6DB99E5);
            c = HH(c, d, a, b, x[k+15], S33, 0x1FA27CF8);
            b = HH(b, c, d, a, x[k+2],  S34, 0xC4AC5665);
            a = II(a, b, c, d, x[k+0],  S41, 0xF4292244);
            d = II(d, a, b, c, x[k+7],  S42, 0x432AFF97);
            c = II(c, d, a, b, x[k+14], S43, 0xAB9423A7);
            b = II(b, c, d, a, x[k+5],  S44, 0xFC93A039);
            a = II(a, b, c, d, x[k+12], S41, 0x655B59C3);
            d = II(d, a, b, c, x[k+3],  S42, 0x8F0CCC92);
            c = II(c, d, a, b, x[k+10], S43, 0xFFEFF47D);
            b = II(b, c, d, a, x[k+1],  S44, 0x85845DD1);
            a = II(a, b, c, d, x[k+8],  S41, 0x6FA87E4F);
            d = II(d, a, b, c, x[k+15], S42, 0xFE2CE6E0);
            c = II(c, d, a, b, x[k+6],  S43, 0xA3014314);
            b = II(b, c, d, a, x[k+13], S44, 0x4E0811A1);
            a = II(a, b, c, d, x[k+4],  S41, 0xF7537E82);
            d = II(d, a, b, c, x[k+11], S42, 0xBD3AF235);
            c = II(c, d, a, b, x[k+2],  S43, 0x2AD7D2BB);
            b = II(b, c, d, a, x[k+9],  S44, 0xEB86D391);
            a = addUnsigned(a, AA);
            b = addUnsigned(b, BB);
            c = addUnsigned(c, CC);
            d = addUnsigned(d, DD);
        }
        return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d)).toLowerCase();
    },

    // DiceBear 10.x Glyphs avatar URL generator (free, public SVG glyphs)
    getDiceBearGlyphUrl(sessionId) {
        const seed = encodeURIComponent(String(sessionId || 'session'));
        return `https://api.dicebear.com/10.x/shadows/svg?seed=${seed}`;
    },

    getAvatarUrl(sessionId) {
        return this.getDiceBearGlyphUrl(sessionId);
    },

    getGravatarUrl(sessionId) {
        return this.getDiceBearGlyphUrl(sessionId);
    },

    // Deterministic 5x5 SVG identicon (100% offline fallback)
    getIdenticonSvgDataUri(sessionId, size = 48) {
        const hash = this.md5(String(sessionId || 'session'));
        const hue = parseInt(hash.substring(0, 3), 16) % 360;
        const color = `hsl(${hue}, 65%, 48%)`;
        const bg = `hsl(${hue}, 35%, 96%)`;

        const cells = [];
        const cellSize = Math.round(size / 6);
        const offset = Math.round(size / 12);

        for (let col = 0; col < 3; col++) {
            for (let row = 0; row < 5; row++) {
                const charIdx = (col * 5 + row) + 3;
                const charVal = parseInt(hash.charAt(charIdx % hash.length), 16);
                if (charVal % 2 === 0) {
                    cells.push({ x: col * cellSize + offset, y: row * cellSize + offset, size: cellSize });
                    if (col < 2) {
                        cells.push({ x: (4 - col) * cellSize + offset, y: row * cellSize + offset, size: cellSize });
                    }
                }
            }
        }

        const rects = cells.map(c => `<rect x="${c.x}" y="${c.y}" width="${c.size}" height="${c.size}" fill="${color}" />`).join('');
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}"><rect width="${size}" height="${size}" rx="${Math.round(size/6)}" fill="${bg}"/>${rects}</svg>`;
        return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
    },

    // Deterministic avatar object for components
    getSessionAvatarSvg(sessionId, size = 18) {
        const url = this.getDiceBearGlyphUrl(sessionId);
        const fallback = this.getIdenticonSvgDataUri(sessionId, 48);
        return {
            url,
            fallback,
            bg: '#f8fafc',
            border: '#e2e8f0'
        };
    },

    // Country Flags (powered by flag-icons & vector SVG)
    getCountryFlag(countryCode, { size = 16, className = '' } = {}) {
        if (!countryCode || countryCode === 'UN' || countryCode === 'Unknown' || countryCode === 'XX') {
            return `<span class="country-flag-unknown ${className}" title="Unknown">🌐</span>`;
        }
        const code = String(countryCode).toLowerCase().trim();
        const width = Math.round(size * 1.33);
        return `<span class="country-flag-wrap ${className}" title="${code.toUpperCase()}"><img src="https://flagcdn.com/${code}.svg" alt="${code.toUpperCase()}" class="country-flag-img fi fi-${code}" width="${width}" height="${size}" loading="lazy" onerror="this.onerror=null; this.outerHTML='<span class=\\'fi fi-${code}\\'></span>';" /></span>`;
    },

    // Rich Vector Brand Icons for Browsers (via jsdelivr browser-icon@1.0.2 with SVG fallback)
    getBrowserIcon(browser, size = 14, className = '') {
        const b = (browser || '').toLowerCase().trim();
        let slug = null;

        if (b.includes('operagx') || b.includes('opera gx')) {
            slug = 'operagx';
        } else if (b.includes('opera') || b.includes('opr')) {
            slug = 'opera';
        } else if (b.includes('edge') || b.includes('edg')) {
            slug = 'edge';
        } else if (b.includes('brave')) {
            slug = 'brave';
        } else if (b.includes('samsung')) {
            slug = 'samsunginternet';
        } else if (b.includes('vivaldi')) {
            slug = 'vivaldi';
        } else if (b.includes('tor')) {
            slug = 'tor';
        } else if (b.includes('duckduckgo')) {
            slug = 'duckduckgo';
        } else if (b.includes('yandex')) {
            slug = 'yandex';
        } else if (b.includes('chromium')) {
            slug = 'chromium';
        } else if (b.includes('chrome') || b.includes('crios')) {
            slug = 'chrome';
        } else if (b.includes('firefox') || b.includes('fxios')) {
            slug = 'firefox';
        } else if (b.includes('safari')) {
            slug = 'safari';
        } else if (b === 'ie' || b.includes('internet explorer') || b.includes('msie') || b.includes('trident')) {
            slug = 'internetexplorer';
        } else if (b.includes('ucbrowser') || b.includes('uc browser') || b === 'uc') {
            slug = 'ucbrowser';
        } else if (b.includes('huawei')) {
            slug = 'huawei';
        } else if (b.includes('maxthon')) {
            slug = 'maxthon';
        } else if (b.includes('falkon')) {
            slug = 'falkon';
        } else if (b.includes('midori')) {
            slug = 'midori';
        } else if (b.includes('electron')) {
            slug = 'electron';
        } else if (b.includes('qutebrowser')) {
            slug = 'qutebrowser';
        }

        const fallback = `<svg viewBox=\'0 0 24 24\' width=\'${size}\' height=\'${size}\' fill=\'none\' stroke=\'#64748b\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'brand-icon-svg\' style=\'vertical-align: middle;\'><rect width=\'20\' height=\'16\' x=\'2\' y=\'4\' rx=\'2\'/><path d=\'M2 9h20M6 6.5h.01M9 6.5h.01M12 6.5h.01\'/></svg>`;

        if (!slug) {
            return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="brand-icon-svg" style="vertical-align: middle;"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="M2 9h20M6 6.5h.01M9 6.5h.01M12 6.5h.01"/></svg>`;
        }

        const safeBrowser = String(browser || slug).replace(/"/g, '&quot;');
        return `<img src="https://cdn.jsdelivr.net/npm/browser-icon@1.0.2/icon/${slug}.svg" alt="${safeBrowser}" width="${size}" height="${size}" class="brand-icon-svg ${className}" style="vertical-align: middle; display: inline-block; object-fit: contain;" loading="lazy" onerror="this.onerror=null; this.outerHTML='${fallback}';" />`;
    },

    // Rich Vector Brand Icons for Operating Systems (via simple-icons on jsDelivr with SVG fallback)
    getOsIcon(os, size = 14, className = '') {
        const o = (os || '').toLowerCase().trim();
        let slug = null;

        if (o.includes('android')) {
            slug = 'android';
        } else if (o.includes('ios') || o.includes('iphone') || o.includes('ipad') || o.includes('tvos') || o.includes('watchos')) {
            slug = 'apple';
        } else if (o.includes('mac') || o.includes('macos') || o.includes('darwin') || o.includes('osx')) {
            slug = 'apple';
        } else if (o.includes('windows') || o.includes('win')) {
            slug = 'windows';
        } else if (o.includes('chrome')) {
            slug = 'chromeos';
        } else if (o.includes('kali')) {
            slug = 'kalilinux';
        } else if (o.includes('ubuntu')) {
            slug = 'ubuntu';
        } else if (o.includes('mint')) {
            slug = 'linuxmint';
        } else if (o.includes('fedora')) {
            slug = 'fedora';
        } else if (o.includes('debian')) {
            slug = 'debian';
        } else if (o.includes('arch')) {
            slug = 'archlinux';
        } else if (o.includes('opensuse') || o.includes('suse')) {
            slug = 'opensuse';
        } else if (o.includes('centos')) {
            slug = 'centos';
        } else if (o.includes('raspbian') || o.includes('raspberry')) {
            slug = 'raspberrypi';
        } else if (o.includes('freebsd')) {
            slug = 'freebsd';
        } else if (o.includes('openbsd')) {
            slug = 'openbsd';
        } else if (o.includes('solaris')) {
            slug = 'solaris';
        } else if (o.includes('haiku')) {
            slug = 'haiku';
        } else if (o.includes('tizen')) {
            slug = 'tizen';
        } else if (o.includes('linux')) {
            slug = 'linux';
        }

        const fallback = `<svg viewBox=\'0 0 24 24\' width=\'${size}\' height=\'${size}\' fill=\'none\' stroke=\'#64748b\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\' class=\'brand-icon-svg\' style=\'vertical-align: middle;\'><rect width=\'18\' height=\'18\' x=\'3\' y=\'3\' rx=\'2\'/><path d=\'M9 3v18M3 9h18\'/></svg>`;

        if (!slug) {
            return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="brand-icon-svg" style="vertical-align: middle;"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18M3 9h18"/></svg>`;
        }

        const safeOs = String(os || slug).replace(/"/g, '&quot;');
        return `<img src="https://cdn.jsdelivr.net/npm/simple-icons@latest/icons/${slug}.svg" alt="${safeOs}" width="${size}" height="${size}" class="brand-icon-svg ${className}" style="vertical-align: middle; display: inline-block; object-fit: contain; opacity: 0.75;" loading="lazy" onerror="this.onerror=null; this.outerHTML='${fallback}';" />`;
    },

    // Devices
    getDeviceIcon(device, size = 14) {
        const d = (device || '').toLowerCase();
        if (d === 'mobile') return this.get('smartphone', { size });
        if (d === 'tablet') return this.get('tablet', { size });
        return this.get('monitor', { size });
    }
};

window.Icons = Icons;
