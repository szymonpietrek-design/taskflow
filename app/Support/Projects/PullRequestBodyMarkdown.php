<?php

namespace App\Support\Projects;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/** Safely render provider-controlled PR Markdown, including Dependabot's HTML <details> blocks. */
final class PullRequestBodyMarkdown
{
    private const TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i',
        'del', 's', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'table', 'thead',
        'tbody', 'tr', 'td', 'th', 'a', 'details', 'summary', 'kbd', 'div', 'span',
    ];

    private const REMOVE_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'video', 'audio',
        'source', 'link', 'meta', 'base', 'template', 'noscript', 'img',
    ];

    public function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        // Keep GitHub Flavored Markdown features but do not install the
        // DisallowedRawHtmlExtension bundled with GithubFlavoredMarkdownExtension.
        // That extension escapes <script> into visible text before our DOM
        // sanitizer can remove the dangerous element *and its contents*.
        // All rendered HTML is still treated as untrusted and sanitized below.
        $environment = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $html = (new MarkdownConverter($environment))->convert($markdown)->getContent();

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            // A fixed wrapper ensures only the converted fragment is returned.
            $document->loadHTML('<?xml encoding="UTF-8"?><div id="taskflow-pr-body">'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }
        foreach (iterator_to_array($root->childNodes) as $child) {
            $this->clean($child);
        }
        $safe = '';
        foreach ($root->childNodes as $child) {
            $safe .= $document->saveHTML($child);
        }
        return $safe;
    }

    private function clean(DOMNode $node): void
    {
        if ($node instanceof DOMComment || ! ($node instanceof DOMElement) && $node->nodeType !== XML_TEXT_NODE) {
            $node->parentNode?->removeChild($node);
            return;
        }
        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->clean($child);
        }
        if (! in_array($tag, self::TAGS, true)) {
            // Preserve text, but never allow an unknown element's attributes.
            while ($node->firstChild !== null) {
                $node->parentNode?->insertBefore($node->firstChild, $node);
            }
            $node->parentNode?->removeChild($node);
            return;
        }

        $href = $tag === 'a' ? $node->getAttribute('href') : '';
        while ($node->attributes->length > 0) {
            $node->removeAttributeNode($node->attributes->item(0));
        }
        if ($tag === 'a' && $this->safeHttpUrl($href)) {
            $node->setAttribute('href', $href);
            $node->setAttribute('rel', 'noopener noreferrer nofollow');
            $node->setAttribute('target', '_blank');
        }
        // In particular, raw HTML cannot force open a long Dependabot details block.
    }

    private function safeHttpUrl(string $href): bool
    {
        if (strlen($href) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $href)) {
            return false;
        }
        $url = parse_url($href);
        return $url !== false && is_array($url)
            && in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            && ! empty($url['host'])
            && ! isset($url['user']) && ! isset($url['pass']);
    }
}
