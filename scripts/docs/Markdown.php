<?php

declare(strict_types=1);

/**
 * A small Markdown renderer for the documentation in docs/.
 *
 * It covers what the docs use, nothing more: headings, paragraphs, flat
 * lists (with GitHub's "- [x]" task items), fenced code, quotes, tables,
 * images, links, inline code, bold and italic. Raw HTML is always escaped. Headings get GitHub-style anchors, so
 * links such as `operations/README.md#backups` keep working on the site.
 */
final class Markdown
{
    /** @var list<array{level: int, id: string, text: string}> */
    public array $headings = [];

    /** @var array<string, int> */
    private array $usedIds = [];

    /** @param Closure(string): string $resolveUrl rewrites link and image targets */
    public function __construct(private readonly Closure $resolveUrl) {}

    public function render(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        return $this->blocks($lines);
    }

    /** GitHub's anchor for a heading: lowercase, punctuation dropped, spaces turned into hyphens. */
    public static function slug(string $text): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', mb_strtolower(trim($text)));
        return str_replace(' ', '-', (string) $slug);
    }

    /** @param list<string> $lines */
    private function blocks(array $lines): string
    {
        $html = '';
        $count = count($lines);
        for ($i = 0; $i < $count;) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            if (preg_match('/^```\s*([\w+-]*)\s*$/', $line, $fence)) {
                $code = [];
                for ($i++; $i < $count && !preg_match('/^```\s*$/', $lines[$i]); $i++) {
                    $code[] = $lines[$i];
                }
                $i++;
                $html .= $this->codeBlock(implode("\n", $code), strtolower($fence[1]));
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $heading)) {
                $html .= $this->heading(strlen($heading[1]), $heading[2]);
                $i++;
                continue;
            }
            if (str_starts_with($line, '>')) {
                $quote = [];
                for (; $i < $count && str_starts_with($lines[$i], '>'); $i++) {
                    $quote[] = preg_replace('/^>\s?/', '', $lines[$i]);
                }
                $html .= '<blockquote>' . $this->blocks($quote) . "</blockquote>\n";
                continue;
            }
            if (str_starts_with(trim($line), '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-{3,}/', $lines[$i + 1])) {
                $rows = [];
                for (; $i < $count && str_starts_with(trim($lines[$i]), '|'); $i++) {
                    $rows[] = $lines[$i];
                }
                $html .= $this->table($rows);
                continue;
            }
            if (preg_match('/^([-*]|\d+\.)\s+/', $line, $marker)) {
                $html .= $this->listBlock($lines, $i, is_numeric($marker[1][0]), $end);
                $i = $end;
                continue;
            }
            $paragraph = [];
            for (; $i < $count && trim($lines[$i]) !== '' && !$this->startsBlock($lines, $i); $i++) {
                $paragraph[] = trim($lines[$i]);
            }
            $text = implode(' ', $paragraph);
            $html .= preg_match('/^!\[[^\]]*\]\([^)]+\)$/', $text)
                ? '<figure>' . $this->inline($text) . "</figure>\n"
                : '<p>' . $this->inline($text) . "</p>\n";
        }
        return $html;
    }

    /** @param list<string> $lines */
    private function startsBlock(array $lines, int $i): bool
    {
        $line = $lines[$i];
        return (bool) preg_match('/^(```|#{1,6}\s|>|([-*]|\d+\.)\s+)/', $line)
            || (str_starts_with(trim($line), '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-{3,}/', $lines[$i + 1]));
    }

    /**
     * A flat list; indented lines continue the current item, and a blank line
     * followed by another item of the same kind keeps the list going.
     *
     * @param list<string> $lines
     */
    private function listBlock(array $lines, int $i, bool $ordered, ?int &$end = null): string
    {
        $pattern = $ordered ? '/^\d+\.\s+/' : '/^[-*]\s+/';
        $items = [];
        $count = count($lines);
        while ($i < $count) {
            if (preg_match($pattern, $lines[$i])) {
                $items[] = preg_replace($pattern, '', $lines[$i]);
            } elseif (trim($lines[$i]) !== '' && preg_match('/^\s{2,}\S/', $lines[$i]) && $items) {
                $items[count($items) - 1] .= ' ' . trim($lines[$i]);
            } elseif (trim($lines[$i]) === '' && isset($lines[$i + 1]) && preg_match($pattern, $lines[$i + 1])) {
                // A blank line between two items.
            } else {
                break;
            }
            $i++;
        }
        $end = $i;
        $tag = $ordered ? 'ol' : 'ul';
        $html = '';
        $isTaskList = false;
        foreach ($items as $item) {
            // "[x] Done" and "[ ] To do", as GitHub renders them.
            if (preg_match('/^\[([ xX])\]\s+(.*)$/s', trim($item), $task)) {
                $isTaskList = true;
                $done = $task[1] !== ' ';
                $html .= '<li class="task' . ($done ? ' is-done' : '') . '"><span class="task-box" aria-hidden="true"></span>'
                    . '<span class="visually-hidden">' . ($done ? 'Done' : 'Not done yet') . ': </span>' . $this->inline($task[2]) . '</li>';
            } else {
                $html .= '<li>' . $this->inline(trim($item)) . '</li>';
            }
        }
        return "<{$tag}" . ($isTaskList ? ' class="task-list"' : '') . ">{$html}</{$tag}>\n";
    }

    private function heading(int $level, string $text): string
    {
        $plain = trim(strip_tags($this->inline($text)));
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5);
        $id = self::slug($plain);
        if (isset($this->usedIds[$id])) {
            $id .= '-' . $this->usedIds[$id]++;
        } else {
            $this->usedIds[$id] = 1;
        }
        $this->headings[] = ['level' => $level, 'id' => $id, 'text' => $plain];
        $anchor = $level > 1 ? '<a class="anchor" href="#' . $id . '" aria-label="Link to this section">#</a>' : '';
        return "<h{$level} id=\"{$id}\">" . $this->inline($text) . $anchor . "</h{$level}>\n";
    }

    /** @param list<string> $rows */
    private function table(array $rows): string
    {
        $cells = static fn(string $row): array => array_map('trim', explode('|', trim(trim($row), '|')));
        $header = $cells($rows[0]);
        $html = '<div class="table-wrap"><table><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . $this->inline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach (array_slice($rows, 2) as $row) {
            $html .= '<tr>' . implode('', array_map(fn(string $cell): string => '<td>' . $this->inline($cell) . '</td>', $cells($row))) . '</tr>';
        }
        return $html . "</tbody></table></div>\n";
    }

    private function codeBlock(string $code, string $language): string
    {
        return '<div class="code-block"><button class="copy" type="button" aria-label="Copy code" title="Copy">'
            . '<svg class="icon-copy" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>'
            . '<svg class="icon-check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></button>'
            . '<pre><code' . ($language !== '' ? ' class="language-' . htmlspecialchars($language) . '"' : '') . '>'
            . Highlighter::highlight($code, $language) . "</code></pre></div>\n";
    }

    /** Inline code, images, links, bold and italic. Everything else is escaped. */
    public function inline(string $text): string
    {
        $codes = [];
        $text = (string) preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codes): string {
            $codes[] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES) . '</code>';
            return "\x00" . (count($codes) - 1) . "\x00";
        }, $text);
        $text = htmlspecialchars($text, ENT_QUOTES);
        $text = (string) preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', fn(array $m): string => '<img src="' . htmlspecialchars(($this->resolveUrl)(html_entity_decode($m[2], ENT_QUOTES)), ENT_QUOTES) . '" alt="' . $m[1] . '" loading="lazy">', $text);
        $text = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
            $url = ($this->resolveUrl)(html_entity_decode($m[2], ENT_QUOTES));
            $external = preg_match('#^https?://#', $url) ? ' target="_blank" rel="noopener"' : '';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $external . '>' . $m[1] . '</a>';
        }, $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $text);
        return (string) preg_replace_callback("/\x00(\\d+)\x00/", static fn(array $m): string => $codes[(int) $m[1]], $text);
    }
}

/** Colours code blocks at build time, so the pages ship no highlighting script. */
final class Highlighter
{
    private const RULES = [
        'json' => ['str' => '"(?:[^"\\\\]|\\\\.)*"', 'num' => '-?\b\d+(?:\.\d+)?\b', 'kw' => '\b(?:true|false|null)\b'],
        'js' => ['com' => '\/\/[^\n]*', 'str' => '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`[^`]*`', 'kw' => '\b(?:const|let|var|function|return|if|else|new|await|async|true|false|null|undefined)\b', 'num' => '\b\d+(?:\.\d+)?\b'],
        'bash' => ['com' => '(?<![\w$])#[^\n]*', 'str' => '"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\'', 'kw' => '(?<=^|\n|&& |\| )[a-z][\w.-]*', 'attr' => '(?<=\s)--?[\w-]+'],
        'ini' => ['com' => '(?<=^|\n)\s*[;#][^\n]*', 'attr' => '(?<=^|\n)[\w.-]+(?=\s*=)', 'str' => '"[^"\n]*"'],
        'nginx' => ['com' => '#[^\n]*', 'str' => '"[^"\n]*"|\'[^\'\n]*\'', 'kw' => '(?<=^|\n)\s*[a-z_]+(?=\s)'],
        'caddy' => ['com' => '#[^\n]*', 'str' => '"[^"\n]*"', 'kw' => '(?<=^|\n)\s*[a-z_]+(?=\s)'],
    ];

    public static function highlight(string $code, string $language): string
    {
        $language = ['sh' => 'bash', 'shell' => 'bash', 'javascript' => 'js', 'conf' => 'nginx'][$language] ?? $language;
        if ($language === 'html' || $language === 'xml') {
            return self::markup($code);
        }
        $rules = self::RULES[$language] ?? null;
        if ($rules === null) {
            return htmlspecialchars($code, ENT_QUOTES);
        }
        $pattern = '/' . implode('|', array_map(static fn(string $class, string $regex): string => "(?P<{$class}>{$regex})", array_keys($rules), $rules)) . '/';
        return self::tokens($code, $pattern, array_keys($rules));
    }

    /** HTML: tag names, attribute names and values, comments. Text between tags stays plain. */
    private static function markup(string $code): string
    {
        $html = '';
        $offset = 0;
        preg_match_all('/<!--.*?-->|<\/?[a-zA-Z][^>]*\/?>|<\/?[a-zA-Z][^>]*$/s', $code, $tags, PREG_OFFSET_CAPTURE);
        foreach ($tags[0] as [$tag, $position]) {
            $html .= htmlspecialchars(substr($code, $offset, $position - $offset), ENT_QUOTES);
            $html .= str_starts_with($tag, '<!--')
                ? '<span class="tok-com">' . htmlspecialchars($tag, ENT_QUOTES) . '</span>'
                : self::tokens($tag, '/(?P<kw>^<\/?[a-zA-Z][\w-]*|\/?>$)|(?P<attr>[\w:-]+)(?==)|(?P<str>"[^"]*"|\'[^\']*\')/', ['kw', 'attr', 'str']);
            $offset = $position + strlen($tag);
        }
        return $html . htmlspecialchars(substr($code, $offset), ENT_QUOTES);
    }

    /** @param list<string> $classes */
    private static function tokens(string $code, string $pattern, array $classes): string
    {
        $html = '';
        $offset = 0;
        preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            [$text, $position] = $match[0];
            if ($text === '' || $position < $offset) {
                continue;
            }
            $class = 'plain';
            foreach ($classes as $name) {
                if (isset($match[$name]) && $match[$name][1] !== -1 && $match[$name][0] !== '') {
                    $class = $name;
                    break;
                }
            }
            $html .= htmlspecialchars(substr($code, $offset, $position - $offset), ENT_QUOTES);
            $html .= '<span class="tok-' . $class . '">' . htmlspecialchars($text, ENT_QUOTES) . '</span>';
            $offset = $position + strlen($text);
        }
        return $html . htmlspecialchars(substr($code, $offset), ENT_QUOTES);
    }
}
