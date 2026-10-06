// Progressive enhancement only. All page content lives in index.html.
if (window.lucide) window.lucide.createIcons();

const copyBtn = document.querySelector('.terminal-copy');
if (copyBtn) {
  copyBtn.addEventListener('click', async () => {
    const textToCopy = copyBtn.getAttribute('data-copy');
    if (!textToCopy) return;

    const onCopySuccess = () => {
      copyBtn.classList.add('copied');
      copyBtn.setAttribute('title', 'Copied!');
      setTimeout(() => {
        copyBtn.classList.remove('copied');
        copyBtn.setAttribute('title', 'Copy commands');
      }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      try {
        await navigator.clipboard.writeText(textToCopy);
        onCopySuccess();
        return;
      } catch (e) {
        // Fallback
      }
    }

    const textarea = document.createElement('textarea');
    textarea.value = textToCopy;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      onCopySuccess();
    } finally {
      document.body.removeChild(textarea);
    }
  });
}

